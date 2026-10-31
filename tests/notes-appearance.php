<?php

declare(strict_types=1);
require __DIR__.'/notes-revision.php';
// Verifica a atualização de uma linha antiga sem tocar no banco de uso diário.
$pdo->exec('CREATE TEMPORARY TABLE quick_notes_legacy_test (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,project_id BIGINT UNSIGNED NULL,body TEXT NOT NULL)');
try {
    $pdo->prepare('INSERT INTO quick_notes_legacy_test(tenant_id,user_id,project_id,body) VALUES (?,?,?,?)')->execute([$tenant,$owner,$projectId,'Nota anterior à migration']);
    $pdo->exec(str_replace('ALTER TABLE quick_notes','ALTER TABLE quick_notes_legacy_test',file_get_contents(dirname(__DIR__).'/database/migrations/013_quick_note_appearance.sql')));
    $pdo->exec("UPDATE quick_notes_legacy_test SET color='blue'");
    $pdo->exec(str_replace('quick_notes','quick_notes_legacy_test',file_get_contents(dirname(__DIR__).'/database/migrations/014_quick_note_urgency.sql')));
    check($pdo->query('SELECT color FROM quick_notes_legacy_test')->fetchColumn()==='orange','Migration converte azul em laranja');
    $pdo->exec("UPDATE quick_notes_legacy_test SET color=DEFAULT");
    $old=$pdo->query('SELECT * FROM quick_notes_legacy_test')->fetch();
    check($old['body']==='Nota anterior à migration'&&(int)$old['user_id']===$owner&&(int)$old['project_id']===$projectId&&$old['title']===''&&$old['color']==='yellow','Migration preserva notas antigas');
} finally {$pdo->exec('DROP TEMPORARY TABLE quick_notes_legacy_test');}
$note=$notes->create($owner,'Corpo preservado',$projectId,'  Título çã  ','orange');
function appearanceRow(int $id):array{global $pdo;return $pdo->query('SELECT * FROM quick_notes WHERE id='.$id)->fetch();}
$row=appearanceRow($note);check($row['title']==='Título çã'&&$row['color']==='orange','Título normalizado e cor persistidos');
$notes->update($owner,$note,'Texto atualizado');$row=appearanceRow($note);check($row['title']==='Título çã'&&$row['color']==='orange','Cliente antigo preserva aparência');
$notes->update($owner,$note,'Corpo',['title'=>'','color'=>'green']);$row=appearanceRow($note);check($row['title']===''&&$row['color']==='green','Título pode ser removido e cor editada');
foreach([str_repeat('á',201),"Linha\nseguinte",[],null,"Inválido\x00"] as $invalid)noteError(422,fn()=>$notes->update($owner,$note,'Texto',['title'=>$invalid]),'Valida título');
foreach(['red','blue','orange;display:none',[],null] as $invalid)noteError(422,fn()=>$notes->create($owner,'Texto',null,'',$invalid),'Valida cor');
noteError(404,fn()=>$notes->update($outside,$note,'Intrusão',['title'=>'Alheio','color'=>'yellow']),'Isolamento na edição');
$csrf=boardLogin($users['owner']['email']);check(noteRequest('/notes/'.$note.'/update',['body'=>'Sem token','title'=>'Falha','color'=>'orange'])[0]===419,'CSRF aparência');
check(noteRequest('/notes/'.$note.'/update',['_token'=>$csrf,'body'=>'Descrição integral','title'=>'<script>título</script>','color'=>'orange'])[0]===200,'Edição HTTP');
$html=request('/projects/'.$projectId)[1];check(str_contains($html,'&lt;script&gt;título&lt;/script&gt;')&&!str_contains($html,'<script>título</script>'),'Título escapado');check(str_contains($html,'data-color="orange"'),'Cor renderizada');
check(noteRequest('/notes/'.$note.'/update',['_token'=>$csrf,'body'=>'Descrição integral','title'=>str_repeat('á',200),'color'=>'yellow'])[0]===200,'Limite UTF-8 aceito');
$task=$notes->convert($owner,$note,$projectId,$column);$row=$pdo->query('SELECT title,description FROM tasks WHERE id='.$task)->fetch();check($row['title']===str_repeat('á',200)&&$row['description']==='Descrição integral','Conversão reaproveita título e preserva corpo');
$legacy=$notes->create($owner,'Sem aparência informada',null);$row=appearanceRow($legacy);check($row['title']===''&&$row['color']==='yellow','Padrões compatíveis');
check(str_contains(request('/notes/new')[1],'name="color"'),'Formulário sem JS oferece cores');
echo "OK: título, cores, limites UTF-8, compatibilidade, conversão, XSS, CSRF e isolamento da aparência.\n";
