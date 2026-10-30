'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');
(async () => {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use somente banco descartável.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000'; const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    const fixture = JSON.parse(await fs.readFile(preview + '/history-fixture.json','utf8'));
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser',args:['--no-sandbox']});
    const context = await browser.newContext({viewport:{width:1440,height:1000}}); const page = await context.newPage(); const errors=[]; page.on('pageerror',error=>errors.push(error.message));
    const projectPath = '/projects/' + fixture.projects.active + '/history';
    async function login(target, role) {
        await target.goto(base+'/login'); await target.locator('#email').fill(fixture.users[role].email); await target.locator('#password').fill('Senha-historico-ui-123');
        await target.getByRole('button',{name:'Entrar',exact:true}).click(); await target.waitForURL('**/dashboard');
    }
    async function audit(target,label) {
        const result = await new AxeBuilder({page:target}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item=>({id:item.id,targets:item.nodes.map(node=>node.target)})),[],label);
    }
    try {
        await login(page,'owner');
        for (const width of [320,390,768,1024,1440]) {
            await page.setViewportSize({width,height:width<640?844:1000});
            for (const [path,name] of [['/history','history-personal'],[projectPath,'history-project']]) {
                await page.goto(base+path); assert.equal(await page.locator('.hi-event').count(),25);
                assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Sem overflow '+name+width);
                assert.equal(await page.locator('.history-page [style], .history-page style, .history-page script').count(),0);
                if(width===390||width===1440){await audit(page,name+width); await page.screenshot({path:`${preview}/${name}-${width}.png`});}
            }
        }
        await page.goto(base+'/history'); await page.locator('#history-filters > summary').focus(); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#action').isVisible(),true);
        assert.equal(await page.locator('#project option').filter({hasText:'Projeto restrito'}).count(),0);
        await page.locator('#project').selectOption(String(fixture.projects.active)); await page.locator('#action').selectOption('task.updated');
        await Promise.all([page.waitForURL(url=>url.searchParams.get('action')==='task.updated'),page.getByRole('button',{name:'Filtrar',exact:true}).click()]);
        assert.equal(await page.locator('.hi-total').innerText(),'27 atividades');
        await page.getByRole('navigation',{name:'Paginação do histórico'}).getByRole('link',{name:'Próxima',exact:true}).click();
        assert.equal(new URL(page.url()).searchParams.get('action'),'task.updated'); assert.equal(new URL(page.url()).searchParams.get('project_id'),String(fixture.projects.active));
        assert.equal(await page.locator('.hi-event').count(),2);
        await page.getByRole('navigation',{name:'Paginação do histórico'}).getByRole('link',{name:'Anterior',exact:true}).click();
        for(const width of [320,390,1440]){
            await page.setViewportSize({width,height:width<640?844:1000});
            await page.goto(base+projectPath+'?action=task.updated');
            const first=page.locator('.hi-event').first(); await first.locator('summary').click();
            assert.match(await first.locator('.hi-changes').innerText(),/Antes/); assert.match(await first.locator('.hi-changes').innerText(),/Depois/);
            assert.match(await first.locator('.hi-changes').innerText(),/<script>/); assert.equal(await page.evaluate(()=>window.historyXss),undefined);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Snapshots longos '+width);
            if(width!==320){await audit(page,'Snapshot '+width); await first.locator('.hi-changes').scrollIntoViewIfNeeded(); await page.screenshot({path:`${preview}/history-changes-${width}.png`});}
        }
        const today=await page.locator('.hi-event time').first().getAttribute('datetime');
        await page.goto(base+projectPath); await page.locator('#history-filters > summary').click();
        await page.locator('#from').fill(today.slice(0,10)); await page.locator('#to').fill(today.slice(0,10));
        await Promise.all([page.waitForURL(url=>url.searchParams.get('from')===today.slice(0,10)),page.getByRole('button',{name:'Filtrar',exact:true}).click()]);
        assert.ok(await page.locator('.hi-event').count()>0,'Período UTC inclusivo');
        assert.ok((await page.locator('.hi-event time').allTextContents()).every(value=>value.endsWith(' UTC')));
        await page.goto(base+projectPath+'?action=comment.created');
        assert.equal(await page.locator('.hi-event').count(),2,'Projeto mostra ações de outros participantes');
        assert.match(await page.locator('.hi-author').allTextContents().then(values=>values.join(' ')),/<script>/);
        await page.locator('.hi-event').last().locator('summary').click(); assert.equal(await page.evaluate(()=>window.historyXss),undefined);
        const taskLink=page.locator('.hi-context-links a[href*="/board?task="]').first(); await taskLink.click();
        await page.waitForURL('**/board?task='+fixture.tasks.active); await page.locator('[data-task-heading]').waitFor();
        assert.equal(await page.locator('[data-task-heading]').innerText(),'Preparar lançamento');
        for(const role of ['owner','admin','member','viewer']){
            const roleContext=await browser.newContext({viewport:{width:390,height:844}});
            try{
                const target=await roleContext.newPage(); await login(target,role); await target.goto(base+projectPath+'?action=invitation.created');
                assert.equal(await target.locator('.hi-event').count(),1);
                const administrative=['owner','admin'].includes(role); assert.equal((await target.content()).includes(fixture.private_email),administrative,'Privacidade '+role);
                assert.equal(await target.locator('.hi-changes').count(),administrative?1:0);
                if(!administrative) await target.locator('.hi-restricted').waitFor();
                await audit(target,role);
                if(role==='member'){
                    await target.goto(base+'/history?action=invitation.created'); assert.equal(await target.locator('.hi-event').count(),1); assert.equal((await target.content()).includes(fixture.private_email),false);
                    await target.goto(base+'/history?action=task.updated'); assert.equal(await target.locator('.hi-event').count(),0,'Pessoal não mostra ações do proprietário');
                }
            }finally{await roleContext.close();}
        }
        await page.goto(base+'/projects/'+fixture.projects.archived+'/history'); await page.locator('.hi-context').waitFor(); await audit(page,'Arquivado');
        await page.goto(base+'/history?from=2000-01-01&to=2000-01-01'); await page.locator('.hi-empty').waitFor(); await audit(page,'Vazio filtrado');
        await page.screenshot({path:preview+'/history-empty-390.png'});
        const invalid=await page.goto(base+projectPath+'?from=2026-12-02&to=2026-12-01'); assert.equal(invalid.status(),422); await audit(page,'Erro 422');
        assert.equal(await page.getByRole('link',{name:'Limpar filtros',exact:true}).getAttribute('href'),projectPath);
        const restricted=await page.goto(base+'/projects/'+fixture.projects.private+'/history'); assert.equal(restricted.status(),404);
        const emptyContext=await browser.newContext({viewport:{width:390,height:844}});
        try{const target=await emptyContext.newPage(); await login(target,'empty'); await target.goto(base+'/history'); await target.locator('.hi-empty').waitFor(); await audit(target,'Conta vazia');}finally{await emptyContext.close();}
        const fallback=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
        try{
            await fallback.addCookies(await context.cookies()); const target=await fallback.newPage(); await target.goto(base+projectPath); await target.locator('#history-filters > summary').click();
            await target.locator('#action').selectOption('task.updated'); await target.getByRole('button',{name:'Filtrar',exact:true}).click();
            const first=target.locator('.hi-event').first(); await first.locator('summary').click(); assert.match(await first.innerText(),/TextoSemEspacos/);
            await target.getByRole('navigation',{name:'Paginação do histórico'}).getByRole('link',{name:'Próxima',exact:true}).click(); assert.equal(await target.locator('.hi-event').count(),2);
        }finally{await fallback.close();}
        assert.deepEqual(errors,[]);
        console.log('OK: históricos 320–1440px, axe A/AA, antes/depois, textos longos/XSS, filtros/paginação/UTC, autores, privacidade por papel, arquivados, links, 422/404 e uso sem JS.');
    }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
