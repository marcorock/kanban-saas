'use strict';
const assert = require('node:assert/strict');
const {default: AxeBuilder} = require('@axe-core/playwright');
const runProfileBrowser = require('./profile-browser.cjs');
runProfileBrowser(async ({page,context,boardUrl,email,password}) => {
    const base=new URL(boardUrl).origin; const preview=process.env.UI_PREVIEW_DIR||'/app/storage/previews';
    async function audit(target,label){const result=await new AxeBuilder({page:target}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();assert.deepEqual(result.violations.map(item=>({id:item.id,targets:item.nodes.map(node=>node.target)})),[],label);}
    async function post(form, expected){const [response]=await Promise.all([page.waitForResponse(response=>response.url()===base+'/settings'&&response.request().method()==='POST'),form.getByRole('button',{name:'Salvar perfil',exact:true}).click()]);assert.equal(response.status(),expected);}
    await page.goto(base+'/settings'); await page.locator('#name').fill('Marina Costa'); await post(page.locator('form[data-settings-form]').first(),303); await page.getByRole('status').filter({hasText:'Nome atualizado.'}).waitFor();
    for(const width of [320,390,768,1024,1440]){
        await page.setViewportSize({width,height:width<640?844:1000});await page.goto(base+'/settings');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Sem overflow '+width);
        assert.equal(await page.locator('.settings-page [style],.settings-page style,.settings-page script').count(),0);
        assert.equal(await page.locator('input[name="email"],input[name="user_id"]').count(),0,'Identidade não é editável');
        assert.equal(await page.locator('#password-settings').getAttribute('open'),null);
        if(width===390||width===1440){await audit(page,'Perfil '+width);await page.screenshot({path:`${preview}/settings-${width}.png`});}
    }
    // Disclosures e mostrar/ocultar senha acessíveis por teclado, sem repopular segredo.
    await page.locator('#password-settings > summary').focus();await page.keyboard.press('Enter');
    await page.locator('#current-password').fill(password);
    const toggle=page.locator('[data-settings-password][aria-controls="current-password"]');await toggle.click();
    assert.equal(await page.locator('#current-password').getAttribute('type'),'text');assert.equal(await toggle.getAttribute('aria-pressed'),'true');
    await page.getByRole('button',{name:'Ocultar senha atual',exact:true}).click();await page.locator('#current-password').fill('');
    await page.locator('#session-settings > summary').click();await audit(page,'Segurança expandida');
    await page.setViewportSize({width:390,height:844});await page.locator('#password-settings').scrollIntoViewIfNeeded();await page.screenshot({path:preview+'/settings-security-390.png'});
    // Validação de nome no servidor preserva dados e foca resumo de erro.
    const profile=page.locator('form[data-settings-form]').first();await page.locator('#name').evaluate(input=>{input.minLength=0;input.value='A';});await post(profile,422);
    await page.locator('[data-settings-errors]').waitFor();assert.equal(await page.locator('#name').inputValue(),'A');
    assert.equal(await page.locator('[data-settings-errors]').evaluate(node=>node===document.activeElement),true);await audit(page,'Erro 422');
    // Sessão expirada do formulário: mantém nome seguro, rejeita escrita e reapresenta token válido.
    await page.locator('#name').fill('Rascunho preservado');await profile.locator('input[name="_token"]').evaluate(input=>{input.value='invalid';});await post(profile,419);
    assert.equal(await page.locator('#name').inputValue(),'Rascunho preservado');assert.equal(await page.locator('.sp-account-name').innerText(),'Marina Costa');
    assert.notEqual(await profile.locator('input[name="_token"]').inputValue(),'invalid');await audit(page,'Erro 419');
    await page.goto(base+'/settings');assert.equal(await page.locator('#name').inputValue(),'Marina Costa');
    // 419 de segurança mantém seção aberta, sem senhas devolvidas no HTML.
    await page.locator('#password-settings > summary').click();await page.locator('#current-password').fill(password);await page.locator('#password').fill('Nova-senha-nao-gravada-789');await page.locator('#confirmation').fill('Nova-senha-nao-gravada-789');
    const secretForm=page.locator('#password-settings form');await secretForm.locator('input[name="_token"]').evaluate(input=>{input.value='invalid';});
    const [denied]=await Promise.all([page.waitForResponse(response=>response.url()===base+'/settings'&&response.request().method()==='POST'),secretForm.getByRole('button',{name:'Alterar senha',exact:true}).click()]);assert.equal(denied.status(),419);
    assert.equal(await page.locator('#password').isVisible(),true);
    for(const id of ['current-password','password','confirmation','session-password']) assert.equal(await page.locator('#'+id).inputValue(),'');
    assert.equal((await page.content()).includes('Nova-senha-nao-gravada-789'),false);
    // Formulários sem JS: perfil, troca de senha e revogação real de outra sessão.
    const fallback=await context.browser().newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
    const observer=await context.browser().newContext();
    try{
        await fallback.addCookies(await context.cookies());const target=await fallback.newPage();await target.goto(base+'/settings');
        assert.equal(await target.locator('[data-settings-password]:visible').count(),0);
        await target.locator('#name').fill('Marina sem JavaScript');await target.getByRole('button',{name:'Salvar perfil',exact:true}).click();await target.getByRole('status').filter({hasText:'Nome atualizado.'}).waitFor();
        const other=await observer.newPage();await other.goto(base+'/login');await other.locator('#email').fill(email);await other.locator('#password').fill(password);await other.getByRole('button',{name:'Entrar',exact:true}).click();await other.waitForURL('**/dashboard');
        await target.locator('#password-settings > summary').click();await target.locator('#current-password').fill(password);await target.locator('#password').fill('Senha-sem-javascript-789');await target.locator('#confirmation').fill('Senha-sem-javascript-789');
        await target.getByRole('button',{name:'Alterar senha',exact:true}).click();await target.getByRole('status').filter({hasText:'Senha alterada.'}).waitFor();
        await other.goto(base+'/settings');await other.waitForURL('**/login');
        await other.locator('#email').fill(email);await other.locator('#password').fill('Senha-sem-javascript-789');await other.getByRole('button',{name:'Entrar',exact:true}).click();await other.waitForURL('**/dashboard');
        await target.locator('#session-settings > summary').click();await target.locator('#session-password').fill('Senha-sem-javascript-789');await target.getByRole('button',{name:'Encerrar demais sessões',exact:true}).click();await target.getByRole('status').filter({hasText:'As demais sessões foram encerradas.'}).waitFor();
        await other.goto(base+'/settings');await other.waitForURL('**/login');
    }finally{await fallback.close();await observer.close();}
    console.log('OK: perfil/configurações 320–1440px, axe A/AA, disclosures, senhas, foco, 422/419, rascunho, identidade, revogação e POST sem JS.');
}).catch(error=>{console.error(error);process.exitCode=1;});
