<?php
include_once ( dirname(__FILE__) . '/../../Assistants/Model.php' );
include_once ( dirname(__FILE__) . '/../../Assistants/vendor/Markdown/Michelf/MarkdownInterface.php' );
include_once ( dirname(__FILE__) . '/../../Assistants/vendor/Markdown/Michelf/Markdown.php' );
include_once ( dirname(__FILE__) . '/../../Assistants/vendor/Markdown/Michelf/MarkdownExtra.php' );
include_once ( dirname(__FILE__) . '/../../UI/include/HTMLWrapper.php' );
include_once ( dirname(__FILE__) . '/../../UI/include/Template.php' );

/**
 * 
 */
class LGitLab extends Model
{

    /**
     * REST actions
     *
     * This function contains the REST actions with the assignments to
     * the functions.
     *
     * @param Component $conf component data
     */
    private $_component = null;
    private $config = array();
    private $secretToken = null;
    public function __construct( )
    {        
        if (file_exists(dirname(__FILE__).'/config.ini')){
            $this->config = parse_ini_file(
                                           dirname(__FILE__).'/config.ini',
                                           TRUE
                                           );
        }
        
        parent::__construct('', dirname(__FILE__), $this, false, false, array('addRequestToParams'=>true));
        $this->run();
    }
    
    public function getHelpButton( $callName, $input, $params = array() )
    {
        $data = json_decode($input, true);
        $res = array('content'=>'');
        if (isset($data['cid'])){
            $URL = $data['externalURI']."/vendor/LGitLab/submit/course/".$data['cid'];
            $content = "<li><a href='{$URL}' class='plain image-button exercise-sheet-images' target='popup' onclick=\"window.open('{$URL}', 'popup', 'width=700,height=600,scrollbars=yes,location=no,directories=no,menubar=no,toolbar=no,status=no,resizable=yes')\" title='info' target='_blank'>GitLab-Hilfe</a></li>";
        
            $res = array('content'=>$content);
        }
        return Model::isOk(json_encode($res)); 
    }
    
    public function umlaute($text){ 
        $search  = array('ä', 'Ä', 'ö', 'Ö', 'ü', 'Ü', 'ß');
        $replace = array('&auml;', '&Auml;', '&ouml;', '&Ouml;', '&uuml;', '&Uuml;', '&szlig;');
        return str_replace($search, $replace, $text);;
    }
    
    public function getSubmitHelp( $callName, $input, $params = array() )
    {
        $file = dirname(__FILE__).'/descriptions/desc.html';
        if (!file_exists($file) || !isset($this->config['MAIN']['externalURI'])){
            return Model::isError("Die Hilfe für GitLab konnte nicht erstellt werden!");
        }

        $h = Template::WithTemplateFile('descriptions/desc.html');
        $h->bind($params);
        $h->bind(array('externalURI'=>$this->config['MAIN']['externalURI']));
        ob_start();
        $h->show();
        $content = ob_get_clean();
        
        $parser = new \Michelf\MarkdownExtra;
        $content = $this->umlaute($content);
        $my_html = $parser->transform($content);
        $contact = isset($this->config['HELP']['contactUrl']) ? $this->config['HELP']['contactUrl'] : null;
        if (isset($contact) && trim($contact) !== ''){
            $contact = '<a href="'.$contact.'">Kontakt</a>';
        }
        
        $content = '<html><head></head><body><link rel="stylesheet" href="'.$this->config['MAIN']['externalURI'].'/UI/css/github-markdown.css" type="text/css"><span class="markdown-body">'.$my_html.$contact.'</span></body></html>';
   
        return Model::isOk($content);
    }
    
    public function getEmptySubmitHelp( $callName, $input, $params = array() )
    {
        $file = dirname(__FILE__).'/descriptions/empty_desc.html';
        if (!file_exists($file) || !isset($this->config['MAIN']['externalURI'])){
            return Model::isError("Die Hilfe für GitLab konnte nicht erstellt werden!");
        }

        $h = Template::WithTemplateFile('descriptions/empty_desc.html');
        $h->bind($params);
        $h->bind(array('externalURI'=>$this->config['MAIN']['externalURI']));
        ob_start();
        $h->show();
        $content = ob_get_clean();
        
        $parser = new \Michelf\MarkdownExtra;
        $content = $this->umlaute($content);
        $my_html = $parser->transform($content);
        $contact = isset($this->config['HELP']['contactUrl']) ? $this->config['HELP']['contactUrl'] : null;
        if (isset($contact) && trim($contact) !== ''){
            $contact = '<a href="'.$contact.'">Kontakt</a>';
        }
        
        $content = '<html><head></head><body><link rel="stylesheet" href="'.$this->config['MAIN']['externalURI'].'/UI/css/github-markdown.css" type="text/css"><span class="markdown-body">'.$my_html.$contact.'</span></body></html>';
   
        return Model::isOk($content);
    }
    
    public function submit( $callName, $input, $params = array() )
    {
        $data = json_decode($input, true);

        // der private-token kann auch als secret-token übermittelt werden
        if (isset($params['request']['headers']['HTTP_X_GITLAB_TOKEN']) && trim($params['request']['headers']['HTTP_X_GITLAB_TOKEN']) != ''){
            $this->secretToken = $params['request']['headers']['HTTP_X_GITLAB_TOKEN'];
        }
        
        if (!isset($data['event_name']) || $data['event_name'] !== 'tag_push'){
            return Model::isProblem("falscher Ereignistyp! Wenn Sie ihr Repository an die Übungsplattform übermitteln wollen, dann müssen Sie einen Tag mit dem Titel SUBMIT_<Serienname>_<Aufgabennummer> erstellen.");
        }
        
        if (!isset($data['project_id'])){
            return Model::isError("Projektnummer fehlt!");
        }
        
        if (!isset($data['checkout_sha'])){
            return Model::isError("Commit-Zusammenhang fehlt!");
        }
        
        if (!isset($data['ref'])){
            return Model::isError("Tag-Daten fehlen!");
        }
        
        if (!isset($params['courseid'])){
            return Model::isError("die Veranstaltungsnummer fehlt!");            
        }
        
        if (!isset($data['project']['namespace'])){
            return Model::isError("der Nutzername fehlt!");            
        }
        
        if (!isset($data['commits'][0]['timestamp'])){
            return Model::isError("es fehlt ein gültiger Zeitstempel (Commit)!");            
        }
        
        $courseIdRaw = $params['courseid']; // die ID der Veranstaltung (muss geprüft werden)
        $userName = $data['project']['namespace']; // das ist der Nutzername (muss geprüft werden)
        $timestampRaw = $data['commits'][0]['timestamp']; // das ist der Einsendezeitpunkt. Format: 2017-02-22T14:10:25+01:00
        $timestamp = strtotime($timestampRaw);
        
        $projectId = $data['project_id'];
        
        // "ref":"refs/tags/SUBMIT_SHEETID_EXERCISEID",
        $tag = $data['ref'];
        $tagRaw = explode("/",$tag);
        end($tagRaw);
        $tagRaw = current($tagRaw); // SUBMIT_SHEETID_EXERCISEID
        $tagRaw = explode("_",$tagRaw); // [SUBMIT, SHEETID, EXERCISEID]

        if (count($tagRaw)!=3){
            return Model::isError("der Tagname ist ungültig");            
        }

        $tagType = $tagRaw[0];
        $sheetName = $tagRaw[1]; // die Übungsserie-ID (muss geprüft werden)
        $exerciseName = strtoupper($tagRaw[2]); // die Aufgaben-ID (muss geprüft werden)
        $checkoutSha = $data['checkout_sha'];
        
        // der TAG muss mit SUBMIT beginnen
        if (strtoupper($tagType) !== 'SUBMIT'){
            return Model::isOk("dieses Tag soll nichts einsenden");
        }
        
        // jetzt müssen courseIdRaw, userName, sheetName und exerciseName noch validiert werden
        $testMe = array('checkoutSha'=>$checkoutSha,'projectId'=>$projectId,'courseIdRaw'=>$courseIdRaw, 'userName'=>$userName, 'sheetName'=>$sheetName, 'exerciseName'=>$exerciseName);
        $val = Validation::open($testMe)
          ->addSet('courseIdRaw',
                   array('satisfy_exists',
                         'valid_identifier',
                         'on_error'=>array('type'=>'error',
                                           'text'=>'die Kursnummer in der URL ist ungültig')))
          ->addSet('userName',
                   array('satisfy_exists',
                         'valid_userName',
                         'on_error'=>array('type'=>'error',
                                           'text'=>'der Nutzername ist ungültig')))          
          ->addSet('sheetName',
                   array('satisfy_exists',
                         'on_error'=>array('type'=>'error',
                                           'text'=>'der Name der Übungsserie ist ungültig (nur 0-9, a-z, A-Z, -_ und Leerzeichen)')))
                                           'text'=>'die Übungsserie konnte nicht erkannt werden')))
          ->addSet('exerciseName',
                   array('satisfy_exists',
                         'valid_alpha_space_numeric',
                         'on_error'=>array('type'=>'error',
                                           'text'=>'der Name der Aufgabe ist ungültig (im Tag)')))
          ->addSet('checkoutSha',
                   array('satisfy_exists',
                         'valid_sha1',
                         'on_error'=>array('type'=>'error',
                                           'text'=>'der Hash des Commits ist ungültig')))
          ->addSet('projectId',
                   array('satisfy_exists',
                         'valid_integer',
                         'on_error'=>array('type'=>'error',
                                           'text'=>'die ID des Repository ist ungültig'))); 

        $result = $val->validate(); // liefert die Ergebnismenge

        if ($val->isValid()){
            // die Eingabe des Nutzers passt zu unseren Vorgaben
            $sheetName = $result['sheetName'];
            $exerciseName = $result['exerciseName'];
            $userName = $result['userName'];
            $courseIdRaw = $result['courseIdRaw'];
            $projectId = $result['projectId'];
            $checkoutSha = $result['checkoutSha'];
        } else {
              // wenn die Eingabe nicht validiert werden konnte, können hier die
              // Fehlermeldungen behandelt werden
              $errorText = array();
              $errors = $val->getNotifications();
              foreach($errors as $error){
                  if (!isset($error['text'])){
                      continue;
                  }
                  $errorText[]=$error['text'];
              }
            return Model::isError($this->sendTagError($projectId, $tag, "fehlerhafte Eingabe => ".implode(',',$errorText))); 
        }

        // wenn die Form stimmt, dann können wir die Daten gegenprüfen
        $exerciseSheets = Model::call('getCourseExerciseSheets', array('courseid'=>$courseIdRaw), '', 200, 'Model::isOk', array(), 'Model::isProblem', array(), null);
        
        if ($exerciseSheets['status'] == 200){
            $exerciseSheets = ExerciseSheet::decodeExerciseSheet($exerciseSheets['content']);
        } else {
            return Model::isError($this->sendTagError($projectId, $tag, "die Übungsserien konnten nicht abgerufen werden!")); 
        }
        
        // ab hier werden die Kursnummer, Seriennummer und Aufgabennummer bestimmt, sowie die Aufgabennamen berechnet
        $sheetId = null;
        $exerciseId = null;
        $courseId = null;
        $mySheet=null;
        foreach($exerciseSheets as $sheet){
            $currentSheetName = strtoupper($sheet->getSheetName());
            $currentSheetName2 = str_replace(array('_', "\t"), array('',''), $currentSheetName);
            $currentSheetName = str_replace(array('_', ' ', "\t"), array('','',''), $currentSheetName);
            if ($currentSheetName === strtoupper($sheetName) || $currentSheetName2 === strtoupper($sheetName)){
                $sheetId = $sheet->getId();
                $courseId = $sheet->getCourseId();
                $mySheet = $sheet;
                
                // nun müssen wir noch die Aufgabe finden
                $namesOfExercises = array();
                $alphabet = range('A', 'Z');
                $count=null;
                $exercises = json_decode(json_encode($sheet->getExercises()),true);
                $exercises = LArraySorter::orderBy($exercises, 'link', SORT_ASC, 'linkName', SORT_ASC);
            
                foreach ($exercises as $key => $exercise){
                    $exerciseId = $exercise['id'];

                    if ($count===null || $exercises[$count]['link'] != $exercise['link']){
                        $count=$key;
                        $namesOfExercises[$exercise['link']] = $exerciseId;
                        $subtask = 0;
                    }else{
                        $subtask++;
                        $namesOfExercises[$exercise['link'].$alphabet[$subtask]] = $exerciseId;
                        $namesOfExercises[$exercises[$count]['link'].$alphabet[0]] = $exercises[$count]['id'];
                    }
                }
                
                if (isset($namesOfExercises[$exerciseName])){
                    $exerciseId = $namesOfExercises[$exerciseName];
                } else {    
                    return Model::isError($this->sendTagError($projectId, $tag, "die Aufgabe existiert nicht!"));                     
                }
                break;
            }
        }
        
        if ($sheetId === null){    
            return Model::isError($this->sendTagError($projectId, $tag, "die Übungsserie existiert nicht!"));  
        }
        
        if ($courseIdRaw != $courseId){
            return Model::isError($this->sendTagError($projectId, $tag, "die übergebene Veranstaltungsnummer passt nicht zur Veranstaltung der Übungsserie!")); 
        }
        
        // der Nutzername muss noch aufgelöst werden
        $userData = Model::call('getUser', array('userid'=>$userName), '', 200, 'Model::isOk', array(), 'Model::isProblem', array(), null);
        
        if ($userData['status'] == 200){
            $userData = User::decodeUser($userData['content']);
        } else {
            return Model::isError($this->sendTagError($projectId, $tag, "ungültiger Nutzer!")); 
        }
        $userId = $userData->getId();
        
        // Ich benötige noch die Gruppe des Nutzers
        $groupData = Model::call('getGroup', array('userid'=>$userId, 'sheetid'=>$sheetId), '', 200, 'Model::isOk', array(), 'Model::isProblem', array(), null);
        if ($groupData['status'] == 200){
            $groupData = Group::decodeGroup($groupData['content']);
        } else {
            return Model::isError($this->sendTagError($projectId, $tag, "ungültige Gruppe!")); 
        }
        
        // hier müssen wir noch die Veranstaltung extrahieren
        $course = null;
        foreach($userData->getCourses() as $courseStatus){
            $subCourse = $courseStatus->getCourse();
            if ($subCourse->getId() == $courseId){
                $course = $subCourse;
                break;
            }
        }
        
        if ($course === null){
            return Model::isError($this->sendTagError($projectId, $tag, "ich konnte keine passende Veranstaltung zu diesem Nutzer finden!")); 
        }
        
        // ab diesem Punkt besitzen wir die korrekte courseId, sheetId, exerciseId, userId, timestamp, projectId, checkoutSha
        //var_dump(array('courseId'=>$courseId,'sheetId'=>$sheetId,'exerciseId'=>$exerciseId,'userId'=>$userId, 'timestamp'=>$timestamp, 'projectId'=>$projectId, 'checkoutSha'=>$checkoutSha));
        
        // wenn alles stimmt, dann rufen wir nun ein Archiv des aktuellen Repo ab
        $token = null;
        if (isset($this->secretToken)){ // wenn ein secretToken gefunden wurde, dann wird dieser genutzt
            $token = urlencode($this->secretToken);
        } else {
            if (isset($this->config['GITLAB']['private_token'])){
                $token = $this->config['GITLAB']['private_token'];
            } else {
                $token = 'noToken';
            }
        }
        
        file_put_contents('einsendungen.dat', 'tag: '.$tag.' course:'.$courseIdRaw.' date:'.date(DATE_RFC822).' user:'.$userName.' sheet:'.$sheetName.' exercise:'.$exerciseName."\n", FILE_APPEND);
        
        $url = $this->config['GITLAB']['gitLabUrl'].'/api/v4/projects/'.$projectId.'/repository/archive?'.'private_token='.$token.'&sha='.$checkoutSha;
        $tempFile = $this->config['DIR']['temp'].'/'.sha1($url);

        $res = Request::download($tempFile, $url, true);

        if ($res['status'] == 200 && isset($res['content'])){
            // die Datei befindet sich nun in $tempFile

            // Quelle: http://stackoverflow.com/questions/17830276/run-windows-command-in-php
            $disp = $res['headers']['Content-Disposition'];
            $filename=null; // das soll der Dateiname werden
                // this catches filenames between Quotes
            if(preg_match('/.*filename=[\'\"]([^\'\"]+)/', $disp, $matches))
            { $filename = $matches[1]; }
                // if filename is not quoted, we take all until the next space
            else if(preg_match("/.*filename=([^ ]+)/", $disp, $matches))
            { $filename = $matches[1]; }
            
            if ($filename !== null){
                // jetzt können wir das Ding als Einsendung speichern
                
                // wir müssen aber noch prüfen, ob der Übungszeitraum abgelaufen ist
                $isExpired=null;
                $hasStarted=null;

                if ($mySheet->getEndDate() !== null && $mySheet->getStartDate() !== null){
                    // bool if endDate of sheet is greater than the actual date
                    $isExpired = $timestamp > intval($mySheet->getEndDate()); 

                    // bool if startDate of sheet is greater than the actual date
                    $hasStarted = $timestamp > intval($mySheet->getStartDate());

                    if ($isExpired){
                        $allowed = Course::containsSetting($course,'AllowLateSubmissions');

                        ///set_error("Der Übungszeitraum ist am ".date('d.m.Y  -  H:i', $upload_data['exerciseSheet']['endDate'])." abgelaufen!");
                        if ($allowed  === null || $allowed==1){
                            // verhindert verspaetete Einsendungen
                            return Model::isError($this->sendTagError($projectId, $tag, "der Übungszeitraum ist abgelaufen, am ".date('d.m.Y  -  H:i', $mySheet->getEndDate())));
                        } else {
                            return Model::isError($this->sendTagError($projectId, $tag, "der Übungszeitraum ist abgelaufen, am ".date('d.m.Y  -  H:i', $mySheet->getEndDate())));
                        }

                    } elseif (!$hasStarted){
                        return Model::isError($this->sendTagError($projectId, $tag, "der Übungszeitraum hat noch nicht begonnen")); 
                    }

                } else {
                    return Model::isError($this->sendTagError($projectId, $tag, "kein Übungszeitraum gefunden")); 
                }

                $uploadFile = File::createFile(null,$filename,null,$timestamp,null,null);
                $uploadFile->setBody(Reference::createReference($tempFile));

                $uploadSubmission = Submission::createSubmission(null,$userId,null,$exerciseId,'von '.$this->config['GITLAB']['gitLabUrl'],1,$timestamp,null, $groupData->getLeader()->getId());
                $uploadSubmission->setFile($uploadFile);
                $uploadSubmission->setExerciseName($exerciseName);
                $uploadSubmission->setSelectedForGroup('1');
                
                if ($isExpired){
                    $uploadSubmission->setAccepted(0);
                }

                $positive = function($input, $object, $projectId, $tag){
                    // die Einsendung konnte erfolgreich abgelegt werden
                    return Model::isOk($object->sendTagSuccess($projectId, $tag, "Die Einsendung wurde gespeichert!")); 
                };
                
                $negative = function($object, $projectId, $tag){
                    return Model::isError($object->sendTagError($projectId, $tag, "Die Einsendung konnte nicht gespeichert werden!")); 
                };

                // jetzt wird die Einsendung gespeichert
                return Model::call('postSubmission', array('courseid'=>$courseId), Submission::encodeSubmission($uploadSubmission), 201, $positive, array('object'=>$this, 'projectId'=>$projectId, 'tag'=>$tag), $negative, array('object'=>$this, 'projectId'=>$projectId, 'tag'=>$tag), null);
            } else {
                return Model::isError($this->sendTagError($projectId, $tag, "ich konnte den Dateinamen der Einsendung nicht ermitteln")); 
            }
        } else {
            return Model::isError($this->sendTagError($projectId, $tag, "das Repo konnte nicht bei GitLab abgerufen werden")); 
        }
    }
    
    /**
     * Returns status code 200, if this component is correctly installed for the platform
     *
     * Called when this component receives an HTTP GET request to
     * /link/exists/platform.
     */
    public function getExistsPlatform( $callName, $input, $params = array() )
    {
        Logger::Log(
                    'starts GET GetExistsPlatform',
                    LogLevel::DEBUG
                    );
                   
        if (!file_exists(dirname(__FILE__).'/config.ini')){
            return Model::isProblem();
        }
      
        return Model::isOk();
    }
   
    /**
     * Removes the component from the platform
     *
     * Called when this component receives an HTTP DELETE request to
     * /platform.
     */
    public function deletePlatform( $callName, $input, $params = array() )
    {
        Logger::Log(
                    'starts DELETE DeletePlatform',
                    LogLevel::DEBUG
                    );
        if (file_exists(dirname(__FILE__).'/config.ini') && !unlink(dirname(__FILE__).'/config.ini')){
            return Model::isProblem();
        }
       
        return Model::isCreated();
    }
   
    /**
     * Adds the component to the platform
     *
     * Called when this component receives an HTTP POST request to
     * /platform.
     */
    public function addPlatform( $callName, $input, $params = array() )
    {
        Logger::Log(
                    'starts POST AddPlatform',
                    LogLevel::DEBUG
                    );
       
        $file = dirname(__FILE__).'/config.ini';
        $text = "[DIR]\n".
                "temp = \"".str_replace(array("\\","\""),array("\\\\","\\\""),str_replace("\\","/",$input->getTempDirectory()))."\"\n";
                
        $text .= "[MAIN]\n".
                "externalURI = \"".str_replace(array("\\","\""),array("\\\\","\\\""),str_replace("\\","/",$input->getExternalUrl()))."\"\n";
                
        $settings = $input->getSettings();
        
        if (isset($settings->contactUrl)){
            $text .= "[HELP]\n";
            $text .= "contactUrl = \"".str_replace(array("\\","\""),array("\\\\","\\\""),str_replace("\\","/",$settings->contactUrl))."\"\n";
        }
        
        $text .= "[GITLAB]\n";
        
        if (isset($settings->LGitLab_gitLabUrl)){
            $text .= "gitLabUrl = \"".str_replace(array("\\","\""),array("\\\\","\\\""),str_replace("\\","/",$settings->LGitLab_gitLabUrl))."\"\n";
        }
        
        if (isset($settings->LGitLab_private_token)){
            $text .= "private_token = \"".str_replace(array("\\","\""),array("\\\\","\\\""),str_replace("\\","/",$settings->LGitLab_private_token))."\"\n";
        }
        
        if (!@file_put_contents($file,$text)){
            Logger::Log(
                        'POST AddPlatform failed, config.ini no access',
                        LogLevel::ERROR
                        );

            return Model::isProblem();
        }  

        $platform = new Platform();
        $platform->setStatus(201);
       
        return Model::isCreated($platform);
    }
    
    public function sendTagError($projectId, $tag, $message){
        $message = ':exclamation: '.$message.' :exclamation:';
        return $this->sendTag($projectId, $tag, $message);
    }
    
    public function sendTagSuccess($projectId, $tag, $message){
        $message = ':white_check_mark: '.$message;
        return $this->sendTag($projectId, $tag, $message);
    }
    
    public function sendTag($projectId, $tag, $message){
        $tagName = end(explode('/',$tag));
        
        $token = null;
        // wenn wir einen secretToken gefunden haben, dann wird dieser verwendet
        if (isset($this->secretToken)){
            $token = urlencode($this->secretToken);
        } else {            
            if (isset($this->config['GITLAB']['private_token'])){
                $token = $this->config['GITLAB']['private_token'];
            } else {
                $token = 'noToken';
            }
        }
        $url = $this->config['GITLAB']['gitLabUrl'].'/api/v4/projects/'.urlencode($projectId).'/repository/tags/'.urlencode($tagName).'/release?'.'description='.urlencode($message).'&private_token='.$token;
        
        if (isset($token)){
            Request::post($url, array(),  '', false);
        }
        return $message;
    }

}
