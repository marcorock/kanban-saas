'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs/promises');
const {chromium}=require('playwright-core');
const {default:AxeBuilder}=require('@axe-core/playwright');
(async()=>{
    if(process.env.AUTH_TEST_DATABASE!=='disposable')throw new Error('Use banco descartável.');
    const base=process.env.AUTH_TEST_URL||'http://127.0.0.1:8000';const preview=process.env.UI_PREVIEW_DIR||'/app/storage/previews';
    const fixture=JSON.parse(await fs.readFile(preview+'/notifications-fixture.json','utf8'));
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium-browser',args:['--no-sandbox']});
    const context=await browser.newContext();const page=await context.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));
    async function login(target,role){await target.goto(base+'/login');await target.locator('#email').fill(fixture.users[role].email);await target.locator('#password').fill('Senha-notificacao-ui-123');await target.getByRole('button',{name:'Entrar',exact:true}).click();await target.waitForURL('**/dashboard');}
    async function audit(target,label){const result=await new AxeBuilder({page:target}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();assert.deepEqual(result.violations.map(item=>({id:item.id,targets:item.nodes.map(node=>node.target)})),[],label);}
    try{
        await login(page,'member');
        for(const width of [320,390,768,1024,1440]){
            await page.setViewportSize({width,height:width<640?844:1000});await page.goto(base+'/notifications');await page.locator('.notification-badge:not([hidden])').waitFor();
            assert.equal(await page.locator('.nt-item').count(),20);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Central sem overflow '+width);
            assert.equal(await page.locator('.nt-page [style],.nt-page style,.nt-page script').count(),0);
            assert.equal((await page.content()).includes('PRIVADO-DESCRICAO'),false);assert.equal((await page.content()).includes('PRIVADO-COMENTARIO'),false);
            if(width===390||width===1440){await audit(page,'Central '+width);await page.screenshot({path:`${preview}/notifications-${width}.png`});}
            assert.equal((await page.goto(base+'/settings')).status(),200);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Header sem overflow '+width);assert.equal(await page.locator('.app-page-context').evaluate(node=>node.scrollWidth<=node.clientWidth+1),true,'Título não invade o sino '+width);
        }
        await page.goto(base+'/notifications');await page.locator('.notification-badge:not([hidden])').waitFor();const before=Number(await page.locator('[data-notification-count]').innerText());
        await page.getByRole('button',{name:'Marcar como lida',exact:true}).first().click();await page.waitForFunction(value=>Number(document.querySelector('[data-notification-count]').textContent)===value,before-1);
        assert.equal(await page.locator('.nt-item').first().getByText('Lida',{exact:true}).count(),1);
        await page.getByRole('navigation',{name:'Paginação de notificações'}).getByRole('link',{name:'Próxima',exact:true}).click();assert.ok(await page.locator('.nt-item').count()<20);
        await page.locator('.nt-item a[href*="/board?task="]').first().click();await page.waitForURL('**/board?task='+fixture.task_id);await page.locator('[data-task-heading]').waitFor();assert.equal(await page.locator('[data-task-heading]').innerText(),'Preparar a próxima entrega');
        await page.goto(base+'/settings');await page.locator('#notification-settings > summary').focus();await page.keyboard.press('Enter');
        assert.equal(await page.locator('[name="bell_enabled"]').isChecked(),true);assert.equal(await page.locator('[name="push_enabled"]').isChecked(),false);assert.equal(await page.locator('[name="email_enabled"]').isChecked(),false);
        await page.locator('[name="bell_enabled"]').uncheck();await page.locator('[name="email_enabled"]').check();await page.getByRole('button',{name:'Salvar preferências',exact:true}).click();await page.getByRole('status').filter({hasText:'Preferências de notificações atualizadas.'}).waitFor();
        await page.locator('#notification-settings > summary').click();assert.equal(await page.locator('[name="bell_enabled"]').isChecked(),false);assert.equal(await page.locator('[name="email_enabled"]').isChecked(),true);await audit(page,'Preferências');await page.screenshot({path:preview+'/notification-preferences-390.png'});
        const fallback=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
        try{await fallback.addCookies(await context.cookies());const target=await fallback.newPage();await target.goto(base+'/notifications');await target.getByRole('button',{name:'Marcar como lida',exact:true}).first().click();await target.goto(base+'/settings');await target.locator('#notification-settings > summary').click();await target.locator('[name="bell_enabled"]').check();await target.getByRole('button',{name:'Salvar preferências',exact:true}).click();await target.getByRole('status').filter({hasText:'Preferências de notificações atualizadas.'}).waitFor();}finally{await fallback.close();}
        const other=await browser.newContext({viewport:{width:390,height:844}});
        try{const target=await other.newPage();await login(target,'owner');await target.goto(base+'/notifications');assert.equal(await target.locator('.nt-item').count(),0);await audit(target,'Conta sem avisos');}finally{await other.close();}
        assert.deepEqual(errors,[]);console.log('OK: sino/central 320–1440px, contador, leitura, paginação, links, preferências, axe, isolamento e uso sem JS.');
    }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
