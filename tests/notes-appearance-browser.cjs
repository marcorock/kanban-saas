'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs/promises');
const {chromium}=require('playwright-core');
const {default:AxeBuilder}=require('@axe-core/playwright');
(async()=>{
    if(process.env.AUTH_TEST_DATABASE!=='disposable')throw new Error('Use banco descartável.');
    const fixture=JSON.parse(await fs.readFile('/app/storage/previews/notes-fixture.json','utf8'));
    const base=process.env.AUTH_TEST_URL||'http://127.0.0.1:8000';
    const browser=await chromium.launch({executablePath:'/usr/bin/chromium-browser',args:['--no-sandbox']});
    try {
        const context=await browser.newContext();const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
        await page.goto(base+'/login');await page.locator('#email').fill(fixture.users.owner.email);await page.locator('#password').fill('Senha-notas-ui-123');await page.getByRole('button',{name:'Entrar',exact:true}).click();await page.waitForURL('**/dashboard');
        await page.goto(base+'/tasks');await page.keyboard.press('Control+k');assert.equal(await page.locator('[name="color"]:checked').inputValue(),'yellow','Média selecionada por padrão');assert.equal(await page.locator('.qn-colors legend').textContent(),'Prioridade');assert.equal(await page.locator('.qn-color-choice small').count(),0);await page.locator('#quick-note-body').fill('Texto com várias linhas\n'.repeat(180));await page.locator('#quick-note-heading').fill('Ideia urgente');await page.getByRole('radio',{name:'Alta',exact:true}).check();await page.locator('#quick-note-body').focus();await page.keyboard.press('Enter');await page.waitForFunction(()=>document.querySelector('.qn-card-title')?.textContent==='Ideia urgente');
        assert.equal(await page.locator('.qn-card').first().getAttribute('data-color'),'orange');
        for(const width of [320,390,768,1024,1440]){
            await page.setViewportSize({width,height:900});await page.reload();
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Sem overflow '+width);
            if(width<1024)assert.equal(await page.locator('.app-bottom-nav').evaluate(n=>new Set([...n.children].filter(e=>getComputedStyle(e).display!=='none').map(e=>Math.round(e.getBoundingClientRect().top))).size),1,'Navegação em uma linha '+width);
            assert.equal(await page.locator('.qn-body').first().evaluate(n=>n.scrollHeight>n.clientHeight),true,'Rolagem interna '+width);
            const boxes=await page.locator('.qn-card').first().evaluate(n=>{const c=n.getBoundingClientRect();return [...n.querySelectorAll('.qn-card-actions > *')].map(e=>{const b=e.getBoundingClientRect();return b.left>=c.left&&b.right<=c.right&&b.bottom<=c.bottom;});});assert.ok(boxes.every(Boolean),'Ações contidas no card '+width);
            const audit=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();assert.deepEqual(audit.violations.map(v=>({id:v.id,targets:v.nodes.map(n=>n.target)})),[],'Acessibilidade '+width);
            if(width===390)await page.locator('#brainstorm').screenshot({path:'/app/storage/previews/postit-mobile.png'});
        }
        await page.locator('.qn-card-menu summary').first().click();await page.locator('.qn-menu-panel [data-note-edit]').first().click();assert.equal(await page.locator('#quick-note-heading').inputValue(),'Ideia urgente');assert.equal(await page.locator('[name="color"]:checked').inputValue(),'orange');for(const value of ['green','yellow','orange']){await page.locator('[name="color"][value="'+value+'"]').check();const audit=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();assert.deepEqual(audit.violations.map(v=>v.id),[],'Contraste da urgência '+value);}await page.getByRole('radio',{name:'Baixa',exact:true}).check();await page.locator('#quick-note-heading').fill('Ideia revisada');await page.locator('#quick-note-body').focus();await page.keyboard.press('Enter');await page.waitForFunction(()=>document.querySelector('.qn-card-title')?.textContent==='Ideia revisada');assert.equal(await page.locator('.qn-card').first().getAttribute('data-color'),'green');
        await page.keyboard.press('Control+k');assert.equal(await page.locator('#quick-note-heading').inputValue(),'');assert.equal(await page.locator('[name="color"]:checked').inputValue(),'yellow');await page.keyboard.press('Escape');await page.screenshot({path:'/app/storage/previews/postit-desktop.png',fullPage:true});assert.deepEqual(errors,[]);
        console.log('OK: aparência, cores persistidas, edição pelo menu, rolagem, ações e axe 320–1440px.');
    }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
