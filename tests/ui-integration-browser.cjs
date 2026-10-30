'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {default: AxeBuilder} = require('@axe-core/playwright');
const runBoardBrowser = require('./board-browser.cjs');

runBoardBrowser(async ({page,context,boardUrl}) => {
    const base=new URL(boardUrl).origin; const projectPath=new URL(boardUrl).pathname.replace(/\/board$/,'');
    const preview=process.env.UI_PREVIEW_DIR||'/app/storage/previews'; const evidence=[]; const assetErrors=[]; const scriptErrors=[];
    page.on('response',response=>{if(new URL(response.url()).pathname.startsWith('/assets/')&&response.status()>=400)assetErrors.push({path:new URL(response.url()).pathname,status:response.status()});});
    page.on('console',message=>{if(/Content Security Policy|violates.*directive/i.test(message.text()))scriptErrors.push(message.text());});
    // Uma tarefa atribuída, duas subtarefas e progresso real para cruzar os módulos na mesma conta.
    await page.goto(boardUrl);
    const taskId=await page.evaluate(async()=>{
        const root=document.getElementById('kanban');let board=JSON.parse(document.getElementById('board-state').textContent);
        const task=board.columns.flatMap(column=>column.tasks).find(task=>!task.completed_at);
        async function mutate(action,payload){const response=await fetch(root.dataset.endpoint,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({...payload,action,version:board.version,_token:root.dataset.csrf})});const result=await response.json();if(!response.ok)throw new Error(result.message);board=result.board;}
        await mutate('task.update',{task_id:task.id,title:'Planejar a próxima entrega',description:'Preparar, revisar e compartilhar a entrega com a equipe.',priority:'high',due_on:new Date(Date.now()+86400000).toISOString().slice(0,10),assignee_ids:[board.members[0].id]});
        await mutate('checklist.create',{task_id:task.id,title:'Preparação'});
        const response=await fetch(`/projects/${board.project_id}/tasks/${task.id}`,{headers:{Accept:'application/json'}});const data=await response.json();
        const list=data.details.checklists[0].id;
        for(const title of ['Revisar o escopo','Compartilhar a entrega'])await mutate('item.create',{task_id:task.id,checklist_id:list,title});
        return task.id;
    });
    const routes=['/dashboard','/projects','/projects/new',projectPath,projectPath+'/edit',projectPath+'/members',projectPath+'/board','/tasks','/history',projectPath+'/history','/settings'];
    async function structural(path,width,preference='padrão'){
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`Refluxo ${width} ${preference} ${path}`);
        assert.equal(await page.locator('main').count(),1,'Um conteúdo principal');assert.equal(await page.locator('h1').count(),1,'Um título principal');
        assert.equal(await page.locator('html').getAttribute('lang'),'pt-BR');
        assert.equal(await page.locator('.app-sidebar').isVisible(),width>=1024);
        assert.equal(await page.locator('.app-bottom-nav').isVisible(),width<1024);
        const duplicate=await page.evaluate(()=>{const ids=[...document.querySelectorAll('[id]')].map(node=>node.id);return ids.filter((id,index)=>ids.indexOf(id)!==index);});assert.deepEqual(duplicate,[],'IDs únicos '+path);
        evidence.push({path,width,preference,structural:'ok'});
    }
    async function audit(path,width,label='página completa'){
        const result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
        const violations=result.violations.map(item=>({id:item.id,targets:item.nodes.map(node=>node.target)}));assert.deepEqual(violations,[],path+' '+width+' '+label);
        evidence.push({path,width,audit:label,violations:[]});
    }
    for(const width of [320,390,768,1024,1440]){
        await page.setViewportSize({width,height:width<640?844:1000});
        for(const path of routes){assert.equal((await page.goto(base+path)).status(),200);await structural(path,width);if(width===390||width===1440)await audit(path,width);}
    }
    // Preferências do usuário, aplicadas só no teste; nenhum estilo inline entra na aplicação.
    await page.setViewportSize({width:390,height:844});
    for(const path of routes){
        await page.goto(base+path);await page.addStyleTag({content:'html { font-size: 200% !important; }'});await structural(path,390,'texto 200%');
        await page.goto(base+path);await page.addStyleTag({content:'* { line-height: 1.5 !important; letter-spacing: .12em !important; word-spacing: .16em !important; } p { margin-bottom: 2em !important; }'});await structural(path,390,'espaçamento de texto');
    }
    await page.goto(base+'/dashboard');await page.locator('.app-skip-link').focus();await page.keyboard.press('Enter');
    assert.equal(await page.locator('#app-content').evaluate(node=>node===document.activeElement),true,'Pular navegação');
    const more=page.getByRole('button',{name:'Mais',exact:true});await more.click();await page.getByRole('dialog',{name:'Mais opções'}).waitFor();await audit('/dashboard',390,'Mais aberto');
    await page.keyboard.press('Escape');assert.equal(await more.evaluate(node=>node===document.activeElement),true,'Escape retorna a Mais');
    // Jornada real pelos links entre dashboard, listagem pessoal, painel e histórico.
    await page.goto(base+'/tasks');await page.getByRole('link',{name:'Planejar a próxima entrega',exact:true}).click();await page.waitForURL('**/board?task='+taskId);
    await page.locator('[data-task-heading]').waitFor();await page.waitForFunction(()=>document.getElementById('task-panel').classList.contains('show')&&!document.getElementById('task-panel').classList.contains('showing'));
    await audit(projectPath+'/board?task='+taskId,390,'Painel aberto');
    await page.locator('#task-panel-close').press('Escape');await page.locator('#task-panel').waitFor({state:'hidden'});
    assert.equal(await page.getByRole('button',{name:'Planejar a próxima entrega',exact:true}).evaluate(node=>node===document.activeElement),true);
    await page.getByRole('link',{name:'Histórico do projeto',exact:true}).click();assert.match(await page.locator('h2').first().innerText(),/Histórico do projeto/);
    await page.emulateMedia({reducedMotion:'reduce'});await page.goto(boardUrl);await page.getByRole('button',{name:'Próxima coluna',exact:true}).click();await page.waitForFunction(()=>document.getElementById('kanban').scrollLeft>100);
    await page.goto(base+'/settings');await page.locator('#password-settings > summary').click();await page.locator('#session-settings > summary').click();await audit('/settings',390,'Segurança expandida');
    await page.screenshot({path:preview+'/ui-review-settings-390.png'});
    const fallback=await context.browser().newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
    try{await fallback.addCookies(await context.cookies());const target=await fallback.newPage();for(const path of routes){assert.equal((await target.goto(base+path)).status(),200);assert.equal(await target.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Sem JS '+path);}await target.goto(base+'/tasks');await target.locator('.mt-task-title').first().waitFor();}finally{await fallback.close();}
    assert.deepEqual(assetErrors,[],'Assets locais disponíveis');assert.deepEqual(scriptErrors,[],'Sem erros CSP');
    await fs.writeFile(preview+'/ui-integration.json',JSON.stringify({routes:routes.length,viewportWidths:[320,390,768,1024,1440],evidence,assetErrors,scriptErrors},null,2));
    console.log('OK: revisão integrada, 11 rotas, 320–1440px, axe de páginas completas, texto 200%, espaçamento, teclado, jornada, movimento reduzido, assets/CSP e consulta sem JS.');
}).catch(error=>{console.error(error);process.exitCode=1;});
