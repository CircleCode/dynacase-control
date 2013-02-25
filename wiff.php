<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/
/**
 * Web Installer for Freedom
 * XHR PHP Portal
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 *
 * PHP Script called by Web Installer asynchronous requests.
 *
 */

header('Content-type: text/html; charset=UTF-8');

set_include_path(get_include_path() . PATH_SEPARATOR . getcwd() . DIRECTORY_SEPARATOR . 'include');

ini_set('display_errors', 'Off');
ini_set('max_execution_time', 3600);

putenv('WIFF_ROOT=' . getcwd());

require_once ('lib/Lib.System.php');

checkInitServer();

require_once ('class/Class.WIFF.php');
require_once ('class/Class.JSONAnswer.php');
// Autoload required classes
function __autoload($class_name)
{
    require_once 'class/Class.' . $class_name . '.php';
}
// Disabling magic quotes at runtime
// http://fr3.php.net/manual/en/security.magicquotes.disabling.php
if (get_magic_quotes_gpc()) {
    function stripslashes_deep($value)
    {
        $value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
        return $value;
    }
    
    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
    $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}
/**
 * Check for required PHP classes/functions on server
 */
function checkInitServer()
{
    $errors = array();
    // Check for required classes
    foreach (array(
        'DOMDocument',
    ) as $class) {
        if (!class_exists($class, false)) {
            array_push($errors, sprintf("PHP class '%s' not found.", $class));
        }
    }
    // Check for required functions
    foreach (array(
        'json_encode' => 'json',
        'json_decode' => 'json',
        'xml_parse' => 'xml',
        'date' => 'date',
        'preg_match' => 'pcre',
        'pg_connect' => 'pgsql',
        'curl_init' => 'curl'
    ) as $function => $extension) {
        if (!function_exists($function)) {
            array_push($errors, sprintf("PHP function '%s' not found (extension '%s').", $function, $extension));
        }
    }
    // Check for required system commands
    foreach (array(
        'zip',
        'unzip',
        'pg_dump',
        'tar',
        'gzip'
    ) as $cmd) {
        $cmdPath = WiffLibSystem::getCommandPath($cmd);
        if ($cmdPath === false) {
            array_push($errors, sprintf("System command '%s' not found in PATH.", $cmd));
        }
    }
    // Initialize xml conf files
    foreach (array(
        'conf/params.xml',
        'conf/contexts.xml'
    ) as $file) {
        if (is_file($file)) {
            continue;
        }
        if (!is_file(sprintf("%s.template", $file))) {
            array_push($errors, sprintf("Could not find '%s.template' file.", $file, $file));
            continue;
        }
        $fout = fopen($file, 'x');
        if ($fout === false) {
            array_push($errors, sprintf("Could not create '%s' file.", $file));
            continue;
        }
        $content = @file_get_contents(sprintf("%s.template", $file));
        if ($content === false) {
            array_push($errors, sprintf("Error reading content of '%s.template'.", $file));
            continue;
        }
        $ret = fwrite($fout, $content);
        if ($ret === false) {
            array_push($errors, sprintf("Error writing content to '%s'.", $file));
            continue;
        }
        fclose($fout);
    }
    
    if (count($errors) > 0) {
        $msg = join('\n', $errors);
        echo sprintf('alert("%s")', $msg);
        exit(1);
    }
}
/**
 * Format answer for javascript
 * Success attribute is used for recognition by ExtJS
 * @return string formatted
 * @param string $data
 * @param string $error[optional]
 */
function answer($data, $error = null)
{
    if ($data === null) {
        // echo "{error:'".addslashes($error)."',data:'',success:'false'}";
        $answer = new JSONAnswer($data, $error, false);
        echo $answer->encode();
    } else {
        // echo "{error:'".addslashes($error)."',data:'".addslashes($data)."',success:'true'}";
        $answer = new JSONAnswer($data, $error, true);
        echo $answer->encode();
    }
    exit();
}

$wiff = WIFF::getInstance();
// If authentification informations are provided, set them in WIFF
if (isset($_REQUEST['authInfo'])) {
    $wiff->setAuthInfo(json_decode($_REQUEST['authInfo']));
}
// Instanciate context
if (isset($_REQUEST['context'])) {
    $context = $wiff->getContext($_REQUEST['context']);
    if (!$context) {
        answer(null, $wiff->errorMessage);
    }
}
// Request installer version
if (isset($_REQUEST['version'])) {
    $version = $wiff->getVersion();
    if (!$wiff->errorMessage) {
        if (isset($_REQUEST['welcome'])) answer(array(
            "html" => file_get_contents("welcome.html") ,
            "version" => $version
        ));
        else answer($version);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request PHP Info
if (isset($_REQUEST['phpInfo'])) {
    phpinfo();
    exit;
}
// Request installer available version
if (isset($_REQUEST['availVersion'])) {
    $version = $wiff->getAvailVersion();
    if (!$wiff->errorMessage) {
        answer($version);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request if installer need update
if (isset($_REQUEST['needUpdate'])) {
    $needUpdate = $wiff->needUpdate();
    if (!$wiff->errorMessage) {
        answer($needUpdate);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to update installer
if (isset($_REQUEST['update'])) {
    $wiff->update();
    if (!$wiff->errorMessage) {
        answer(true);
    } else {
        answer(null, $wiff->errorMessage);
    }
}

if (isset($_REQUEST['getLogin'])) {
    $login = $wiff->getLogin();
    if (!$wiff->errorMessage) {
        answer($login);
    } else {
        answer(null, $wiff->errorMessage);
    }
}

if (isset($_REQUEST['hasPasswordFile'])) {
    $hasPasswordFile = $wiff->hasPasswordFile();
    if (!$wiff->errorMessage) {
        answer($hasPasswordFile);
    } else {
        answer(null, $wiff->errorMessage);
    }
}

if (isset($_REQUEST['createPasswordFile']) && isset($_REQUEST['login']) && isset($_REQUEST['password'])) {
    $wiff->createPasswordFile($_REQUEST['login'], $_REQUEST['password']);
    if (!$wiff->errorMessage) {
        answer(true);
    } else {
        answer(null, $wiff->errorMessage);
    }
}

if (isset($_REQUEST['registerCrontab'])) {
    $crontab = new Crontab();
    $ret = true;
    if (!$crontab->isAlreadyRegister("control.cron")) {
        $ret = $crontab->registerFile("control.cron");
    }
    if (!$ret) {
        answer(null, $crontab->getErrors());
    } else {
        answer(true);
    }
}
// Request to get a wiff parameter value
if (isset($_REQUEST['getParam'])) {
    $value = $wiff->getParam($_REQUEST['paramName']);
    if (!$wiff->errorMessage) {
        answer($value);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to change all Dynacase-control params' value
if (isset($_REQUEST['changeAllParams'])) {
    $result = $wiff->changeAllParams($_REQUEST);
    if (!$wiff->errorMessage) {
        answer($result);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to change Dynacase-Control params' value
if (isset($_REQUEST['changeParams'])) {
    $result = $wiff->changeParams($_REQUEST['name'], $_REQUEST['value']);
    if (!$wiff->errorMessage) {
        answer($result);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to get all Dynacase-Control params' value
if (isset($_REQUEST['getParamList'])) {
    $paramList = $wiff->getParamList();
    if (!$wiff->errorMessage) {
        $paramsInfo = array();
        foreach ($paramList as $name => $value) {
            $paramsInfo[] = array(
                'name' => $name,
                'value' => $value
            );
        }
        answer($paramsInfo);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to set a wiff parameter value or to create a new one with given value
if (isset($_REQUEST['setParam'])) {
    $wiff->setParam($_REQUEST['paramName'], $_REQUEST['paramValue']);
    if (!$wiff->errorMessage) {
        answer(true);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to import a web installer archive to a given context
if (isset($_REQUEST['importArchive']) && isset($context)) {
    //answer(null,basename( $_FILES['module']['tmp_name']));
    $moduleFile = $context->uploadModule();
    if (!$context->errorMessage) {
        answer($moduleFile);
    } else {
        answer(null, $context->errorMessage);
    }
}
// Request to get global repository list
if (isset($_REQUEST['getRepoList'])) {
    $repoList = $wiff->getRepoList();
    if ($repoList === false) {
        answer(null, $wiff->errorMessage);
    }
    answer($repoList);
}
// Request to add a repo
if (isset($_REQUEST['createRepo']) && $_REQUEST['createRepo'] == true) {
    $return = $wiff->createRepo($_REQUEST['name'], $_REQUEST['description'], $_REQUEST['protocol'], $_REQUEST['host'], $_REQUEST['path'], $_REQUEST['default'], $_REQUEST['authenticated'], $_REQUEST['login'], $_REQUEST['password']);
    if (!$wiff->errorMessage) {
        answer($return);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// request to modify a repo
if (isset($_REQUEST['modifyRepo']) && $_REQUEST['modifyRepo'] == true) {
    $return = $wiff->modifyRepo($_REQUEST['name'], $_REQUEST['description'], $_REQUEST['protocol'], $_REQUEST['host'], $_REQUEST['path'], $_REQUEST['default'], $_REQUEST['authenticated'], $_REQUEST['login'], $_REQUEST['password']);
    if (!$wiff->errorMessage) {
        answer($return);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to delete a repo
if (isset($_REQUEST['deleteRepo'])) {
    $wiff->deleteRepo($_REQUEST['name']);
    if (!$wiff->errorMessage) {
        answer(true);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to authentify a repo
if (isset($_REQUEST['authRepo'])) {
    $repo = $wiff->getRepo($_REQUEST['name']);
    if (!$wiff->errorMessage) {
        $auth = $repo->authentify($_REQUEST['login'], $_REQUEST['password']);
        answer($auth);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to get context list
if (isset($_REQUEST['getContextList'])) {
    $contextList = $wiff->getContextList();
    if ($contextList === false) {
        answer(null, $wiff->errorMessage);
    }
    answer($contextList);
}
// Request to delete a context
if (isset($_REQUEST['deleteContext'])) {
    
    $res = true;
    $err = $wiff->deleteContext($_REQUEST['contextToDelete'], $res, $_REQUEST['deleteContext']);
    if ($res === false) {
        answer(null, $err);
    } else {
        answer($_REQUEST['contextToDelete'], $err);
    }
}
// Request to get archived context list
if (isset($_REQUEST['getArchivedContextList'])) {
    $archivedContextList = $wiff->getArchivedContextList();
    if ($archivedContextList === false) {
        answer(null, $wiff->errorMessage);
    }
    answer($archivedContextList, $wiff->errorMessage);
}
// Request to create new context
if (isset($_REQUEST['createContext'])) {
    $ret = $context = $wiff->createContext($_REQUEST['name'], $_REQUEST['root'], $_REQUEST['desc'], $_REQUEST['url']);
    if ($ret === false) {
        answer(null, $wiff->errorMessage);
        exit(1);
    }
    
    $register = (isset($_REQUEST['register'])) ? true : false;
    
    $ret = $context->setRegister($register);
    if ($ret === false) {
        answer(null, $wiff->errorMessage);
        exit(1);
    }
    
    $repoList = $wiff->getRepoList();
    
    foreach ($repoList as $repo) {
        $postcode = 'repo-' . $repo->name;
        
        str_replace('.', '_', $postcode); // . characters in variables are replaced by _ characters during POST requesting
        if (isset($_REQUEST[$postcode])) {
            $ret = $context->activateRepo($repo->name);
            if ($ret === false) {
                answer(null, $context->errorMessage);
                exit(1);
            }
        }
    }
    // answer(json_encode($context));
    answer($context);
}
// Request to modify an existing context
if (isset($_REQUEST['saveContext'])) {
    $ret = $context = $wiff->saveContext($_REQUEST['name'], $_REQUEST['root'], $_REQUEST['desc'], $_REQUEST['url']);
    if ($ret === false) {
        answer(null, $wiff->errorMessage);
        exit(1);
    }
    
    $ret = $context->setRegister((isset($_REQUEST['register'])) ? true : false);
    if ($ret === false) {
        answer(null, $context->errorMessage);
        exit(1);
    }
    
    $context->deactivateAllRepo();
    
    $repoList = $wiff->getRepoList();
    
    foreach ($repoList as $repo) {
        $postcode = 'repo-' . $repo->name;
        
        str_replace('.', '_', $postcode); // . characters in variables are replaced by _ characters during POST requesting
        if (isset($_REQUEST[$postcode])) {
            $context->activateRepo($repo->name);
            if ($context->errorMessage) {
                answer(null, $context->errorMessage);
                exit(1);
            }
        }
    }
    // answer(json_encode($context));
    answer($context);
}
// Request to archive an existing context
if (isset($_REQUEST['archiveContext'])) {
    /**
     * @var Context $context
     */
    $context = $wiff->getContext($_REQUEST['name']);
    
    if (!$wiff->errorMessage) {
        $archiveDesc = isset($_REQUEST['archiveDesc']) ? $_REQUEST['archiveDesc'] : "";
        $vaultExclude = isset($_REQUEST['vaultExclude']) ? $_REQUEST['vaultExclude'] : "";
        $archiveId = $context->archiveContext($_REQUEST['archiveName'], $archiveDesc, $vaultExclude);
        if ($archiveId === false) {
            answer(null, $context->errorMessage);
        } else {
            answer($archiveId);
        }
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to create a context from an archived context
if (isset($_REQUEST['createContextFromArchive'])) {
    
    if (!$_REQUEST['name']) {
        answer(null, 'A name must be provided.');
    } elseif (!$_REQUEST['root']) {
        answer(null, 'A root must be provided.');
    } elseif (!$_REQUEST['vault_root']) {
        answer(null, 'A vault root must be provided.');
    } elseif (!$_REQUEST['core_pgservice']) {
        answer(null, 'A database service must be provided.');
    } elseif (isset($_REQUEST['remove_profiles'])) {
        if (!$_REQUEST['user_login']) {
            answer(null, 'If you remove profiles, you must specify a user login.');
        } elseif (!$_REQUEST['user_password']) {
            answer(null, 'If you remove profiles, you must specify a user password.');
        }
    }
    $archiveId = $_REQUEST['archiveId'];
    $contextName = $_REQUEST['name'];
    
    $remove_profiles = isset($_REQUEST['remove_profiles']);
    $user_login = $_REQUEST['user_login'];
    $user_password = $_REQUEST['user_password'];
    $clean_tmp_directory = isset($_REQUEST['clean_tmp_directory']);
    $result = $wiff->createContextFromArchive($archiveId, $contextName, $_REQUEST['root'], $_REQUEST['desc'], $_REQUEST['url'], $_REQUEST['vault_root'], $_REQUEST['core_pgservice'], $remove_profiles, $user_login, $user_password, $clean_tmp_directory);
    
    if ($result === false) {
        answer(null, $wiff->errorMessage);
    } else {
        answer($wiff->getContext($contextName));
    }
}

if (isset($_REQUEST['deleteArchive'])) {
    
    $archiveId = $_REQUEST['archiveId'];
    
    $result = $wiff->deleteArchive($archiveId);
    if ($result === false) {
        answer(null, $wiff->errorMessage);
    } else {
        answer($result);
    }
}

if (isset($_REQUEST['downloadArchive'])) {
    
    if ($url = $wiff->downloadArchive($_REQUEST['archiveId'])) {
        answer($url);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to get dependency module list for a module
if (isset($_REQUEST['context']) && isset($_REQUEST['modulelist']) && isset($_REQUEST['getModuleDependencies'])) {
    $dependencyList = $context->getModuleDependencies($_REQUEST['modulelist']);
    
    if ($dependencyList === false) {
        answer(null, $context->errorMessage);
    } else {
        answer($dependencyList);
    }
}
// Request to get dependency module list for an imported module
if (isset($_REQUEST['context']) && isset($_REQUEST['file']) && isset($_REQUEST['getLocalModuleDependencies'])) {
    $dependencyList = $context->getLocalModuleDependencies($_REQUEST['file']);
    
    if ($dependencyList === false) {
        answer(null, $context->errorMessage);
    } else {
        answer($dependencyList);
    }
}
// Request to download module to temporary dir
if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['download'])) {
    $module = $context->getModuleAvail($_REQUEST['module']);
    
    if ($module->download('downloaded')) {
        answer(true);
    } else {
        answer(null, $module->errorMessage);
    }
}
// Request to unpack module in context
/*if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['unpack'])) {
    $module = $context->getModuleDownloaded($_REQUEST['module']);
    
    if ($module->unpack($context->root)) {
        answer(true);
    } else {
        answer(null, $module->errorMessage);
    }
}
// Clean/delete previous module's files and unpack new files
if (isset($_REQUEST['cleanUnpack']) && isset($_REQUEST['context']) && isset($_REQUEST['module'])) {
    $moduleName = $_REQUEST['module'];
    
    $module = $context->getModuleDownloaded($moduleName);
    if ($module === false) {
        answer(null, $context->errorMessage);
    }
    
    $ret = $context->deleteFilesFromModule($moduleName);
    if ($ret === false) {
        answer(null, $context->errorMessage);
    }
    
    $ret = $module->unpack($context->root);
    if ($ret === false) {
        answer(null, $module->errorMessage);
    }
    answer(true);
}*/
// Request to activate a repo list in context
// TODO Unused
if (isset($_REQUEST['context']) && isset($_REQUEST['activateRepo']) && isset($_REQUEST['repo'])) {
    if (!$wiff->errorMessage) {
        foreach ($_REQUEST['repo'] as $repo) {
            $context->activateRepo($repo);
            if (!$context->errorMessage) {
                answer(null, $context->errorMessage);
            }
        }
        // answer(json_encode($context));
        answer($context);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to deactivate a repo list in context
// TODO Unused
if (isset($_REQUEST['context']) && isset($_REQUEST['deactivateRepo']) && isset($_REQUEST['repo'])) {
    if (!$wiff->errorMessage) {
        foreach ($_REQUEST['repo'] as $repo) {
            $context->deactivateRepo($repo);
            if (!$context->errorMessage) {
                answer(null, $context->errorMessage);
            }
        }
        // answer(json_encode($context));
        answer($context);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to get a context installed module list
if (isset($_REQUEST['context']) && isset($_REQUEST['getInstalledModuleListWithUpgrade'])) {
    if (!$wiff->errorMessage) {
        $moduleList = $context->getInstalledModuleListWithUpgrade(true);
        if ($context->errorMessage) {
            answer($moduleList, $context->errorMessage);
        }
        // answer(json_encode($moduleList));
        answer($moduleList);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to get a context available module list
if (isset($_REQUEST['context']) && isset($_REQUEST['getAvailableModuleList'])) {
    if (!$wiff->errorMessage) {
        
        $moduleList = $context->getAvailableModuleList(true);
        if ($context->errorMessage) {
            answer($moduleList, $context->errorMessage);
        }
        // answer(json_encode($moduleList));
        answer($moduleList);
    } else {
        answer(null, $wiff->errorMessage);
    }
}
// Request to get phase list for a given operation
if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['getPhaseList']) && isset($_REQUEST['operation'])) {
    $module = false;
    if (($_REQUEST['operation'] == 'parameter') || ($_REQUEST['operation'] == 'replaced')) {
        $module = $context->getModuleInstalled($_REQUEST['module']);
    } else {
        $module = $context->getModuleDownloaded($_REQUEST['module']);
    }
    
    if (!$module) // If no module was found in installed modules by previous method, then try to get module from available modules
    {
        $module = $context->getModuleAvail($_REQUEST['module']);
    }
    if (!$module) answer(null, sprintf(_("no module for %1\$s (operation: %2\$s)") , $_REQUEST['module'], $_REQUEST['operation']));
    
    $phaseList = $module->getPhaseList($_REQUEST['operation']);
    
    answer($phaseList);
}
// Request to get process list for a given phase
if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['phase']) && isset($_REQUEST['getProcessList']) && isset($_REQUEST['operation'])) {
    $module = false;
    if ($_REQUEST['operation'] == 'parameter' || $_REQUEST['phase'] == 'unregister-module') {
        $module = $context->getModuleInstalled($_REQUEST['module']);
    } else {
        $module = $context->getModuleDownloaded($_REQUEST['module']);
    }
    
    if ($module === false) {
        error_log(__FUNCTION__ . " " . $context->errorMessage);
        answer(null, $context->errorMessage);
    }
    
    $phase = $module->getPhase($_REQUEST['phase']);
    $processList = $phase->getProcessList();
    
    answer($processList);
}
// Request to execute process
if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['phase']) && isset($_REQUEST['process']) && isset($_REQUEST['execute']) && isset($_REQUEST['operation'])) {
    $context = $wiff->getContext($_REQUEST['context']);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Could not get context '%s'.", $_REQUEST['context']) , false);
        echo $answer->encode();
        exit(1);
    }
    
    $module = false;
    if ($_REQUEST['operation'] == 'parameter' || $_REQUEST['phase'] == 'unregister-module') {
        $module = $context->getModuleInstalled($_REQUEST['module']);
    } else {
        $module = $context->getModuleDownloaded($_REQUEST['module']);
    }
    
    if ($module === false) {
        $answer = new JSONAnswer(null, sprintf("Could not get module '%s' in context '%s'.", $_REQUEST['module'], $_REQUEST['context']) , false);
        echo $answer->encode();
        exit(1);
    }
    
    $phase = $module->getPhase($_REQUEST['phase']);
    
    $process = $phase->getProcess(intval($_REQUEST['process']));
    if ($process === null) {
        $answer = new JSONAnswer(null, sprintf("Could not get process '%s' for phase '%s' of module '%s' in context '%s'.", $_REQUEST['process'], $_REQUEST['phase'], $_REQUEST['module'], $_REQUEST['context']) , false);
        echo $answer->encode();
        exit(1);
    }
    
    $result = $process->execute();
    
    if ($result['ret'] === true) {
        if ($phase->name != 'unregister-module') {
            $module->setErrorStatus('');
        }
        $answer = new JSONAnswer(null, $result['output'], true);
        echo $answer->encode();
        exit(1);
    }
    
    if ($phase->name != 'unregister-module') {
        $module->setErrorStatus($phase->name);
    }
    $answer = new JSONAnswer(null, $result['output'], false);
    echo $answer->encode();
    exit(1);
}
// Request to get module parameters
if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['getParameterList']) && isset($_REQUEST['operation'])) {
    $module = false;
    if ($_REQUEST['operation'] == 'parameter') {
        $module = $context->getModuleInstalled($_REQUEST['module']);
    } else {
        $module = $context->getModuleDownloaded($_REQUEST['module']);
    }
    
    if (!$module) {
        $module = $context->getModuleAvail($_REQUEST['module']);
    }
    
    $parameterList = $module->getParameterList();
    // answer(json_encode($parameterList));
    answer($parameterList);
}
// Request to save module parameters
if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['storeParameter']) && isset($_REQUEST['operation'])) {
    $module = false;
    if ($_REQUEST['operation'] == 'parameter') {
        $module = $context->getModuleInstalled($_REQUEST['module']);
    } else {
        $module = $context->getModuleDownloaded($_REQUEST['module']);
    }
    
    if (!$module) {
        $module = $context->getModuleAvail($_REQUEST['module']);
    }
    
    $parameterList = $module->getParameterList();
    
    foreach ($parameterList as $parameter) {
        if (isset($_REQUEST[$parameter->name])) {
            $parameter->value = $_REQUEST[$parameter->name];
            $module->storeParameter($parameter);
        }
    }
    
    answer(true);
}
// Request to run wstop in context
if (isset($_REQUEST['context']) && isset($_REQUEST['wstop'])) {
    $context = $wiff->getContext($_REQUEST['context']);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting context '%s'!", $_REQUEST['context']) , true);
        echo $answer->encode();
        exit(1);
    }
    
    $context->wstop();
    
    answer(true);
}
// Request to run wstart in context
if (isset($_REQUEST['context']) && isset($_REQUEST['wstart'])) {
    $context = $wiff->getContext($_REQUEST['context']);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting context '%s'!", $_REQUEST['context']) , true);
        echo $answer->encode();
        exit(1);
    }
    
    $context->wstart();
    
    answer(true);
}
// Get license agreement
if (isset($_REQUEST['getLicenseAgreement']) && isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['license']) && isset($_REQUEST['operation'])) {
    if ($wiff->getParam("check-license", false, true) == "no") {
        answer(array(
            'agree' => 'yes',
            'license' => ''
        ));
    }
    $context = $wiff->getContext($_REQUEST['context']);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting context '%s'!", $_REQUEST['context']) , true);
        echo $answer->encode();
        exit(1);
    }
    
    $agree = $wiff->getLicenseAgreement($_REQUEST['context'], $_REQUEST['module'], $_REQUEST['license']);
    if ($agree === false) {
        $answer = new JSONAnswer(null, sprintf("Error getLicenseAgreement(%s, %s, %s): %s", $_REQUEST['context'], $_REQUEST['module'], $_REQUEST['license'], $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    if ($agree == 'yes') {
        answer(array(
            'agree' => 'yes',
            'license' => ''
        ));
    }
    
    $module = $context->getModuleDownloaded($_REQUEST['module']);
    if ($module === false) {
        answer(null, sprintf("Error getting downloaded module '%s': %s", $_REQUEST['module'], $context->errorMessage));
    }
    
    $license = $module->getLicenseText();
    
    answer(array(
        'agree' => 'no',
        'license' => $license
    ));
}
// Store license agreement
if (isset($_REQUEST['storeLicenseAgreement']) && isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['license']) && isset($_REQUEST['agree'])) {
    $context = $wiff->getContext($_REQUEST['context']);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting context '%s'!", $_REQUEST['context']) , true);
        echo $answer->encode();
        exit(1);
    }
    
    $agree = $wiff->storeLicenseAgreement($_REQUEST['context'], $_REQUEST['module'], $_REQUEST['license'], $_REQUEST['agree']);
    if ($agree === false) {
        $answer = new JSONAnswer(null, sprintf("Error storeLicenseAgreement(%s, %s, %s, %s): %s", $_REQUEST['context'], $_REQUEST['module'], $_REQUEST['license'], $_REQUEST['agree'], $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    $answer = new JSONAnswer(null);
    echo $answer->encode();
    exit(0);
}
// Set module status
if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['setStatus']) && isset($_REQUEST['status']) && isset($_REQUEST['operation'])) {
    $contextName = $_REQUEST['context'];
    $moduleName = $_REQUEST['module'];
    $status = $_REQUEST['status'];
    $errorstatus = $_REQUEST['errorstatus'];
    $operation = $_REQUEST['operation'];
    
    if ($operation == 'replaced' || $operation == 'parameter') {
        $answer = new JSONAnswer(null, sprintf("Notice: no need to set status on %s operation.", $operation) , true);
        echo $answer->encode();
        exit(0);
    }
    
    $context = $wiff->getContext($contextName);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting context '%s'!", $contextName) , true);
        echo $answer->encode();
        exit(1);
    }
    
    $module = $context->getModuleDownloaded($moduleName);
    if ($module === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting module '%s' in context '%s'!", $moduleName, $contextName) , true);
        echo $answer->encode();
        exit(1);
    }
    
    if ($operation == 'upgrade') {
        $ret = $context->removeModuleInstalled($module->name);
        if ($ret === false) {
            $answer = new JSONAnswer(null, sprintf("Error removing old installed module '%s': %s", $module->name, $context->errorMessage));
            echo $answer->encode();
            exit(1);
        }
    }
    $ret = $module->setStatus('installed', $errorstatus);
    if ($ret === false) {
        $answer = new JSONAnswer(null, sprintf("Error setting installed status on module '%s': %s", $module->name, $module->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    $module->cleanupDownload();
    
    answer(true);
}
// Check repo validity
if (isset($_REQUEST['checkRepoValidity']) && isset($_REQUEST['name'])) {
    $ret = $wiff->checkRepoValidity($_REQUEST['name']);
    answer($ret, $wiff->errorMessage);
}
// Unregister module
/*if (isset($_REQUEST['context']) && isset($_REQUEST['module']) && isset($_REQUEST['unregisterModule'])) {
    $contextName = $_REQUEST['context'];
    $context = $wiff->getContext($contextName);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting context '%s': %s", $contextName, $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    $moduleName = $_REQUEST['module'];
    $ret = $context->removeModule($moduleName);
    if ($ret === false) {
        answer(null, $context->errorMessage);
    }
    
    $ret = $context->deleteFilesFromModule($moduleName);
    if ($ret === false) {
        answer(null, $context->errorMessage);
    }
    
    $ret = $context->deleteManifestForModule($moduleName);
    if ($ret === false) {
        answer(null, $context->errorMessage);
    }
    
    answer(true);
}
// Purge parameters value
if (isset($_REQUEST['purgeUnreferencedParametersValue']) && isset($_REQUEST['context'])) {
    $contextName = $_REQUEST['context'];
    $context = $wiff->getContext($_REQUEST['context']);
    if ($context === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting context '%s': %s", $contextName, $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    $ret = $context->purgeUnreferencedParametersValue();
    if ($ret === false) {
        $answer = new JSONAnswer(null, sprintf("Error purging unreferenced parameters value in context '%s': %s", $contextName, $context->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    answer(true);
}*/

if (isset($_REQUEST['checkInitRegistration'])) {
    $force = (isset($_REQUEST['force']) && $_REQUEST['force'] == 'true') ? true : false;
    $info = $wiff->checkInitRegistration($force);
    if ($info === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting registration info: %s", $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    $answer = new JSONAnswer($info, null, true);
    echo $answer->encode();
    exit(0);
}

if (isset($_REQUEST['tryRegister']) && isset($_REQUEST['mid']) && isset($_REQUEST['ctrlid']) && isset($_REQUEST['login']) && isset($_REQUEST['password'])) {
    $mid = $_REQUEST['mid'];
    $ctrlid = $_REQUEST['ctrlid'];
    $login = $_REQUEST['login'];
    $password = $_REQUEST['password'];
    
    $response = $wiff->tryRegister($mid, $ctrlid, $login, $password);
    if ($response === false) {
        $answer = new JSONAnswer(null, sprintf("Error registering mid/ctrlid : %s", $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    $answer = new JSONAnswer($response, null, true);
    echo $answer->encode();
    exit(0);
}

if (isset($_REQUEST['continueUnregistered'])) {
    $info = $wiff->getRegistrationInfo();
    if ($info === false) {
        $answer = new JSONAnswer(null, sprintf("Error reading registration information: %s", $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    $info['status'] = 'unregistered';
    
    $ret = $wiff->setRegistrationInfo($info);
    if ($ret === false) {
        $answer = new JSONAnswer(null, sprintf("Error writing registration information: %s", $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    answer(true);
}

if (isset($_REQUEST['getRegistrationInfo'])) {
    $info = $wiff->getRegistrationInfo();
    if ($info === false) {
        $answer = new JSONAnswer(null, sprintf("Error reading registration information: %s", $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    $answer = new JSONAnswer($info, null, true);
    echo $answer->encode();
    exit(0);
}

if (isset($_REQUEST['sendContextConfiguration']) && isset($_REQUEST['context'])) {
    $info = $wiff->getRegistrationInfo();
    if ($info === false) {
        $answer = new JSONAnswer(null, sprintf("Could not get registration info: %s", $wiff->errorMessage));
        echo $answer->encode();
        exit(1);
    }
    
    if ($info['status'] != 'registered') {
        $answer = new JSONAnswer(null, sprintf("Dynacase-control is not registeted!"));
        echo $answer->encode();
        exit(1);
    }
    
    $ret = $wiff->sendContextConfiguration($_REQUEST['context']);
    if ($ret === false) {
        $answer = new JSONAnswer(null, $wiff->errorMessage);
        echo $answer->encode();
        exit(1);
    }
    
    answer(true);
}

if (isset($_REQUEST['getConfiguration']) && isset($_REQUEST['context'])) {
    $contextName = $_REQUEST['context'];
    
    $context = $wiff->getContext($contextName);
    if ($context === false) {
        $answer = new JSONAnswer(null, $wiff->errorMessage);
        echo $answer->encode();
        exit(1);
    }
    
    $sc = new StatCollector($wiff, $context);
    $sc->collect();
    $xml = $sc->getXML();
    if ($xml === false) {
        $answer = new JSONAnswer(null, sprintf("Error getting XML statistics."));
        echo $answer->encode();
        exit(1);
    }
    
    $answer = new JSONAnswer(array(
        'stats' => $xml
    ));
    echo $answer->encode();
    exit(0);
}
// Call to get a param value
if (isset($argv)) {
    $paramName = "";
    if (stripos($argv[1], '--getValue=') === 0) {
        $paramName = substr($argv[1], 11);
    }
    
    $xml = new DOMDocument();
    $xml->load($wiff->contexts_filepath);
    
    $xpath = new DOMXPath($xml);
    /**
     * @var DOMElement $parameterNode
     */
    $parameterNode = $xpath->query(sprintf("/contexts/context[@name='%s']/parameters-value/param[@name='%s']", getenv('WIFF_CONTEXT_NAME') , $paramName))->item(0);
    if ($parameterNode) {
        $parameterValue = $parameterNode->getAttribute('value');
        return $parameterValue;
    } else {
        return false;
    }
}

answer(null, "Unrecognized or incomplete call");
