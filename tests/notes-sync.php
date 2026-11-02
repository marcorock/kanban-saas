<?php

declare(strict_types=1);
require __DIR__.'/notes-ownership.php';
$syncProject=newNotesProject($owner,'Sincronização de ideias');
foreach([$member=>'member',$viewer=>'viewer'] as $uid=>$role){$pdo->prepare('INSERT IGNORE INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$uid]);$pdo->prepare('INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,?)')->execute([$tenant,$syncProject,$uid,$role]);}
$syncVersion=fn(int $uid)=>$notes->sync($uid,$syncProject)['token'];
$initial=$syncVersion($member);$private=$notes->create($owner,'SEGREDO-INVISIVEL',$syncProject);
check($syncVersion($member)===$initial,'Nota privada alheia não altera token visível');
$shared=$notes->create($owner,'Texto compartilhado',$syncProject,'Primeira ideia','yellow','project');
check($syncVersion($member)!==$initial,'Criação compartilhada altera token');
$full=$notes->sync($member,$syncProject,1,true);check($full['token']===$syncVersion($member),'Snapshot e metadata compartilham token');
check($full['data']['brainstorm']['total']===1&&!str_contains(json_encode($full),'SEGREDO-INVISIVEL'),'Snapshot limita notas à audiência atual');
check(array_keys($notes->sync($member,$syncProject))===['token'],'Metadata somente token');
$before=$syncVersion($member);$notes->update($owner,$shared,'Novo texto',['title'=>'Ideia revisada','color'=>'orange','revision'=>1]);check($syncVersion($member)!==$before,'Edição e prioridade detectadas');
$before=$syncVersion($member);$pdo->prepare('UPDATE users SET name=? WHERE id=?')->execute(['Proprietário renomeado',$owner]);check($syncVersion($member)!==$before,'Nome dos envolvidos detectado sem alterar revisão');
$before=$syncVersion($member);$notes->transfer($owner,$shared,$member,true,2);check($syncVersion($member)!==$before,'Transferência detectada');
$before=$syncVersion($owner);$notes->update($member,$shared,'Agora privada',['visibility'=>'private','revision'=>3]);check($syncVersion($owner)!==$before&&$notes->sync($owner,$syncProject,1,true)['data']['brainstorm']['total']===1,'Privatização retira nota de quem perdeu propriedade');
$before=$syncVersion($member);$notes->delete($member,$shared,4);check($syncVersion($member)!==$before,'Exclusão detectada');
$inboxBefore=$notes->sync($owner,null)['token'];$inbox=$notes->create($owner,'Inbox entre dispositivos',null);check($notes->sync($owner,null)['token']!==$inboxBefore,'Inbox sincroniza entre dispositivos da própria conta');
$inboxBefore=$notes->sync($owner,null)['token'];$notes->update($owner,$inbox,'Movida',['project_id'=>$syncProject,'revision'=>1]);check($notes->sync($owner,null)['token']!==$inboxBefore,'Movimentação retira do Inbox');
$before=$syncVersion($owner);$column=(int)$boards->state($owner,$syncProject)['columns'][0]['id'];$notes->convert($owner,$inbox,$syncProject,$column,'Título confirmado',2);check($syncVersion($owner)!==$before,'Conversão retira nota e muda versão');
$before=$syncVersion($member);$projects->change($owner,$syncProject,'archive');check($syncVersion($member)!==$before&&!$notes->sync($member,$syncProject,1,true)['data']['noteCanCreate'],'Arquivo muda permissões');$projects->change($owner,$syncProject,'restore');
$before=$syncVersion($member);$pdo->prepare("UPDATE project_members SET role='viewer' WHERE project_id=? AND user_id=?")->execute([$syncProject,$member]);check($syncVersion($member)!==$before&&!$notes->sync($member,$syncProject,1,true)['data']['noteCanConvert'],'Papel atual reconferido');
$csrf=boardLogin($users['owner']['email']);$query='?project_id='.$syncProject;
[$status,$raw,$headers]=request('/notes/version'.$query);check($status===200&&strlen($raw)<100&&str_contains($headers,'no-store')&&!str_contains($raw,'SEGREDO'),'Endpoint leve sem texto/cache');
[$status,$raw]=request('/notes/state'.$query);$state=json_decode($raw,true);check($status===200&&str_contains($state['html'],'data-note-section')&&str_contains($state['html'],'SEGREDO-INVISIVEL'),'Snapshot usa Twig e sessão autorizada');
check(noteRequest('/notes/version?project_id[]=1')[0]===422&&noteRequest('/notes/state?notes_page=-1')[0]===422,'Validação de escopo e página');
$csrf=boardLogin($users['viewer']['email']);$state=noteRequest('/notes/state'.$query)[1];check(!str_contains($state['html'],'SEGREDO-INVISIVEL'),'Visualizador não recebe nota privada');
$csrf=boardLogin($users['outside']['email']);check(noteRequest('/notes/version'.$query)[0]===404&&noteRequest('/notes/state'.$query)[0]===404,'Outro tenant não recebe versão/snapshot');
$pdo->prepare('INSERT IGNORE INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$outside]);check(noteRequest('/notes/version'.$query)[0]===404,'Tenant sozinho não concede acesso');
$pdo->prepare('DELETE FROM project_members WHERE project_id=? AND user_id=?')->execute([$syncProject,$member]);noteError(404,fn()=>$notes->sync($member,$syncProject),'Revogação reconferida');
$csrf=boardLogin($users['member']['email']);check(noteRequest('/notes/state'.$query)[0]===404,'Revogação HTTP');
$csrf=boardLogin($users['owner']['email']);for($i=0;$i<21;$i++)$notes->create($owner,'Página '.$i,$syncProject);
$paged=$notes->sync($owner,$syncProject,99,true);check($paged['data']['brainstorm']['page']===2&&count($paged['data']['brainstorm']['items'])===2,'Página inválida após exclusão é limitada ao último bloco');
check($notes->sync($owner,$syncProject,2)['token']===$paged['token'],'Token usa página canônica');
$before=(int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch()['Value'];$notes->sync($owner,$syncProject);$after=(int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch()['Value'];check($after-$before===4,'Metadata limitada a quatro consultas, sem N+1');
$stable=$syncVersion($owner);$writer=App\Infrastructure\Database::connect($config['database']);$writer->beginTransaction();
try{$writer->prepare('UPDATE quick_notes SET body=?,revision=revision+1 WHERE id=?')->execute(['Ainda não confirmado',$private]);check($syncVersion($owner)===$stable,'Leitura não bloqueia escritor nem publica revisão sem commit');}finally{$writer->rollBack();}
$csrf=boardLogin($users['unverified']['email']);check(noteRequest('/notes/version' .$query)[0]===403,'Verificação exigida');$cookie='';check(request('/notes/version'.$query)[0]===401&&request('/notes/state'.$query)[0]===401,'Autenticação JSON mesmo sem Accept');
echo "OK: sincronização de Post-its, token sem texto, Twig, revisão, Inbox, transferência/visibilidade, membros, permissões, arquivo, paginação e tenant.\n";
