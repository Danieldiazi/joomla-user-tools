<?php
namespace Joomla\CMS\HTML {
    class HTMLHelper {
        public static function _($key) { return '<input type="hidden" name="test-token" value="1">'; }
    }
}
namespace {
    define('_JEXEC',1);
    require __DIR__ . '/../admin/src/Service/Comparison.php';
    require __DIR__ . '/../admin/src/Service/Exclusions.php';
    require __DIR__ . '/../admin/src/Service/Registration.php';
    use Danieldiazi\Component\Usertools\Administrator\Service\Registration;

    $draft = ['text'=>"ana\nluis\nnuevo", 'settings'=>['protect_admins'=>1,'excluded_names'=>'servicio','excluded_groups'=>[8]], 'checked'=>[], 'emails'=>[]];
    $preview = ['nonce'=>'sample-preview'];
    $comparison = ['matched'=>2,'protected'=>3,'blocked'=>0,
        'candidates'=>[(object)['id'=>9,'username'=>'baja']],
        'unknown'=>['nuevo','<script>alert(1)</script>']];
    $result = ['blocked'=>['baja'],'created'=>[['username'=>'nuevo','email'=>'nuevo@example.com','password'=>'Sample-only-aA1!']],'errors'=>[]];
    $groups = array_map(fn($g)=>(object)$g,[
        ['id'=>1,'title'=>'Public'],['id'=>2,'title'=>'Registered'],['id'=>3,'title'=>'Author'],
        ['id'=>4,'title'=>'Editor'],['id'=>5,'title'=>'Publisher'],['id'=>6,'title'=>'Manager'],
        ['id'=>7,'title'=>'Administrator','adminProtected'=>true],['id'=>8,'title'=>'Super Users','adminProtected'=>true]]);
    $pluginInstalled=true; $pluginEnabled=false;
    $reportError=''; $reportPage=['items'=>[], 'total'=>0]; $taskReport=null; $reportPageNumber=1;
    foreach (['home','edit','review','result','schedule','reports'] as $step) {
        ob_start(); require __DIR__ . '/../admin/tmpl/wizard.php'; $html=ob_get_clean();
        if (str_contains($html,'<script>alert')) { throw new \RuntimeException('Unescaped username'); }
        if ($step==='edit' && (str_contains($html,'<select') || str_contains($html,'data-ut-review'))) {
            throw new \RuntimeException('Edit step must not show review or multi-select');
        }
        if ($step==='edit' && (!preg_match('/id="ut-group-7"[^>]* checked disabled/', $html)
            || !preg_match('/id="ut-group-8"[^>]* checked disabled/', $html)
            || !str_contains($html,'Protegido por la exclusión de administradores'))) {
            throw new \RuntimeException('Administrative groups must be checked, disabled and explained');
        }
        if ($step==='review' && (!str_contains($html,'Volver y editar') || str_contains($html,'name="names"'))) {
            throw new \RuntimeException('Review must be separate from input');
        }
        if ($step==='home' && !str_contains($html,'pendiente de habilitar')) { throw new \RuntimeException('Plugin state missing'); }
        if (isset($argv[1])) {
            $dir=$argv[1];
            if (!is_dir($dir)) { mkdir($dir,0777,true); }
            $css=file_get_contents(__DIR__.'/../media/css/admin.css');
            $js=file_get_contents(__DIR__.'/../media/js/admin.js');
            file_put_contents($dir.'/'.$step.'.html','<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>User Tools</title><style>body{margin:0;background:#f5f7fb;font-family:system-ui,sans-serif}'.$css.'</style><body>'.$html.'<script>'.$js.'</script></body></html>');
        }
    }
    $step = 'review';
    foreach ([[(object)['id'=>9,'username'=>'baja']], []] as $candidates) {
        $comparison = ['matched'=>0,'protected'=>1,'blocked'=>0,'candidates'=>$candidates,'unknown'=>['pepito']];
        ob_start(); require __DIR__ . '/../admin/tmpl/wizard.php'; $html=ob_get_clean();
        if (!str_contains($html,'data-ut-no-matches') || !str_contains($html,'Ningún nombre de la lista coincide')) {
            throw new \RuntimeException('Missing no-match warning');
        }
        if (!$candidates && str_contains($html,'ut-warning-danger')) { throw new \RuntimeException('Red only when accounts would be blocked'); }
        if ($candidates && (!str_contains($html,'ut-warning-danger') || !str_contains($html,'todas las cuentas no protegidas') || !str_contains($html,'baja'))) {
            throw new \RuntimeException('No-match review must list accounts to block');
        }
    }
    $comparison['matched'] = 1;
    ob_start(); require __DIR__ . '/../admin/tmpl/wizard.php'; $html=ob_get_clean();
    if (str_contains($html,'data-ut-no-matches')) { throw new \RuntimeException('Warning shown with matches'); }
    echo "OK: wizard and report screens, navigation, checkboxes, plugin state and HTML escaping\n";
}
