<?php
/**
 * Facebook Actions Trait - Facebook php sdk v5.0
 * This class has common actions for facebook actions
 * Requires a Frontend or Backend Module with CoreController and Session Trait
 * Open Graph v2.4
 * Get page extend access token:
 * @link https://developers.facebook.com/tools/explorer/
 * @link https://developers.facebook.com/tools/debug/accesstoken
 * @link https://www.rocketmarketinginc.com/blog/get-never-expiring-facebook-page-access-token/
 * @link [GET albums] https://developers.facebook.com/tools/explorer/?method=GET&path=me%2Falbums&version=v2.5
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Facebook;

//imports
use Phalcon\Exception;
//Facebook PHP SDK
use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\Helpers\FacebookJavaScriptHelper;
use Facebook\Exceptions\FacebookSDKException;
//CrazyCake Utils
use CrazyCake\Services\Redis;
use CrazyCake\Utils\DateHelper;

/**
 * Facebook Actions
 */
trait FacebookActions
{
    /**
     * Set Trait configurations
     */
    abstract public function setConfigurations();

    /**
     * Set Facebook Story Data
     * @param object $object
     */
    abstract public function setStoryData($object);

    /**
     * Config var
     * @var array
     */
    public $fbConfig;

    /**
     * Lib var
     * @var object
     */
    public $fb;

    /**
     * Redis client
     * @var object
     */
    public $redis;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     */
    public function initFacebookSDK()
    {
        //upload path
        if(!is_dir($this->fbConfig["upload_path"]))
            mkdir($this->fbConfig["upload_path"], 0755);

        //set catcher
        $this->redis = new Redis();

        //set Facebook SDK Object
        $this->fb = new \Facebook\Facebook([
            'app_id'     => $this->config->app->facebook->appID,
            'app_secret' => $this->config->app->facebook->appKey,
            //api version
            'default_graph_version' => isset($this->fbConfig["graph_version"]) ? $this->fbConfig["graph_version"] : "v2.5"
        ]);
    }

    /**
     * Publish Facebook feed action
     * @param  object $user The ORM user object
     * @param  string $object The input payload
     * @param array $payload The input payload
     * @param  int $attempt Fallback exception, retry
     * @return string The object id
     */
    public function publish($user_fb, $object, $payload = array(), $attempt = 0)
    {
        //get user facebook data
        $user_fb_class = $this->fbConfig["pages_class"];

        $fallbackAction = false;
        $exception      = false;

        //if user dont have a linked FB account.
        if(is_null($user_fb) || is_null($user_fb->fac))
            $fallbackAction = true;
        //agent type
        else if(!is_null($payload["type"]) && $payload["type"] != "parent")
            $fallbackAction = true;

        //for fallback, switch to user page data
        if($fallbackAction)
            $user_fb = $this->_getFacebookPageUser();

        //print_r($user_fb->toArray());exit; //debug

        try {

            //checkin action only once
            $count = 0;
            if(!$fallbackAction) {

                //create a unique day key for user, qr_hash & time
                $key   = sha1($payload["action"].$payload["qr_hash"].date('Y-m-d'));
                $count = $this->redis->get($key);

                //increment action
                $this->redis->set($key, is_null($count) ? 1 : $count+1);
            }

            //actions
            $method = "_".$payload["action"]."Action";
            //reflection
            $object_id = $this->$method($user_fb, $object, $fallbackAction, $count+1);
            //log response
            $this->logger->debug("FacebookActions::publishAction -> FB Object created, facebook UserId: $user_fb->id. Payload: ".$payload["action"].". Data: ".json_encode($object_id));
            //var_dump($response, $fallbackAction, $object_id);exit;

            //call listener
            return $object_id;
        }
        catch (FacebookSDKException $e) { $exception = $e; }
        catch (Exception $e)            { $exception = $e; }
        catch (\Exception $e)           { $exception = $e; }
        //print_r($exception);exit;

        //an error occurred
        $this->logger->error("FacebookActions::publishAction -> Failed action facebook UserId: ".(is_null($user_fb) ? "null" : $user_fb->id).". Exception: ".$exception->getMessage());

        //only photo has fallback
        if($payload["action"] != "photo")
            throw $exception;

        //if failed more than once, throw exception
        if(empty($attempt))
            $this->publish(null, $object, $payload, 1);
        else
            throw $exception;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Checkin action
     * @param object $user_fb
     * @param object $object The orm object
     * @param boolean $fallbackAction The action is a fallback
     * @param int $count The number of times this action was triggered
     */
    private function _checkinAction($user_fb, $object, $fallbackAction = false, $count = 0)
    {
        if($fallbackAction) // || $count > 1 NOTE: HARDCODED
            throw new Exception("User reached checkin max post times for today");

        //get event facebook object
        $fb_object = $object->{$this->fbConfig["object_fb_relation"]};
        //get message
        $msg = !is_null($fb_object->checkin_text) ? $fb_object->checkin_text : $this->fbConfig["og_default_message"];
        //get place facebook id
        $place_id = !is_null($fb_object->place_id) ? $fb_object->place_id : $this->fbConfig["og_default_place_id"];

        //set params
        $data = [
            "message"      => $msg,
            "place"        => $place_id,
            "link"         => $fb_object->checkin_url,
            "tags"         => $user_fb->id,
            "created_time" => gmdate("Y-m-d\TH:i:s")
        ];
        //print_r($data);exit;

        //set response
        $response = $this->fb->post('me/feed', $data, $user_fb->fac)->getGraphNode();

        return is_object($response) ? $response->getField('id') : null;
    }

    /**
     * Story action
     * TODO: fix start & end date
     * @param object $user_fb
     * @param object $object The orm object
     * @param boolean $fallbackAction The action is a fallback
     * @param int $count The number of times this action was triggered
     */
    private function _storyAction($user_fb, $object, $fallbackAction = false, $count = 0)
    {
        if($fallbackAction) // || $count != 2 //NOTE: HARDCODED
            throw new Exception("User reached story max post times for today");

        //get event facebook object
        $fb_object = $object->{$this->fbConfig["object_fb_relation"]};
        //set message
        $msg = !is_null($fb_object->story_text) ? $fb_object->story_text : $this->fbConfig["og_default_message"];
        //get place facebook id
        $place_id = !is_null($fb_object->place_id) ? $fb_object->place_id : $this->fbConfig["og_default_place_id"];

        //new facebook story object
        $object = $this->_getNewFacebookStoryObject($object);

        $story_object = $this->fbConfig["og_namespace"].":".$this->fbConfig["og_story_object"];
        //push open graph object
        $response = $this->fb->post('me/objects/'.$story_object,
                             ["object" => $object], $user_fb->fac)
                             ->getGraphNode();
        //get OG object id
        $object_id = is_object($response) ? $response->getField('id') : false;

        if(!$object_id)
            throw new Exception("Invalid facebook open graph: ".(int)$object_id);

        //now post this story (SOME Params hardcoded)
        $data = [
            //object
            $this->fbConfig["og_story_object"] => $object_id,
            //common props
            "message"              => $msg,
            "place"                => $place_id,
            "fb:explicitly_shared" => true,
            //aditional props
            "no_feed_story" => false,
            //set time to control action verb
            "start_time" => gmdate("Y-m-d\TH:i:s"),    //example "2015-06-18T18:30:30-00:00"
            "end_time"   => (new \DateTime())->modify('+2 day')->format("Y-m-d\TH:i:s") //end time, HARDCODED
        ];
        //print_r($params);exit;
        $action   = $this->fbConfig["og_namespace"].":".$this->fbConfig["og_story_action"];
        $response = $this->fb->post('/me/'.$action, $data, $user_fb->fac)->getGraphNode();
        //print_r($response);exit;

        return is_object($response) ? $response->getField('id') : null;
    }

    /**
     * Upload a photo
     * @param object $user_fb
     * @param object $object The orm object
     * @param boolean $fallbackAction The action is a fallback
     * @param int $count The number of times this action was triggered
     */
    private function _photoAction($user_fb, $object, $fallbackAction = false, $count = 0)
    {
        if (!$this->request->hasFiles())
           throw new Exception("No files attached to request");

        //get event facebook object
        $fb_object = $object->{$this->fbConfig["object_fb_relation"]};
        $msg = !is_null($fb_object->photo_text) ? $fb_object->photo_text : $this->fbConfig["og_default_message"];

        //get uploaded files
        $uploaded_files = $this->request->getUploadedFiles();
        //get uploaded file
        $file      = current($uploaded_files);
        $file_path = $this->fbConfig["upload_path"].$file->getName();
        $file->moveTo($file_path);

        //set action URI
        if($fallbackAction)
            $action_uri = !is_null($fb_object->album_id) ? $fb_object->album_id."/photos" : $user_fb->id."/photos";
        else
            $action_uri = $user_fb->id."/photos";

        // Upload to a user's profile. The photo will be in the first album in the profile. You can also upload to
        // a specific album by using /ALBUM_ID as the path

        $response  = null;
        $exception = false;

        try {
            $data = [
                'source'  => $this->fb->fileToUpload($file_path),
                'message' => $msg
            ];
            //fb request
            $response = $this->fb->post("/$action_uri", $data, $user_fb->fac)->getGraphNode();

            return is_object($response) ? $response->getField('post_id') : null;
        }
        catch (FacebookSDKException $e) { $response = $e; }
        catch (Exception $e)            { $response = $e; }
        catch (\Exception $e)           { $response = $e; }

         //remove temp file
         if(is_file($file_path))
            unlink($file_path);

        throw $response;
    }

    /**
     * Get Facebook Page as Fallback
     */
    private function _getFacebookPageUser()
    {
        //get a facebook admin
        if(!class_exists($this->fbConfig["pages_class"]))
            throw new Exception("Users Facebook class not found [FB_PAGES_CLASS]");

        $objectClass = $this->fbConfig["pages_class"];

        $page = $objectClass::findFirst("app_id = '".$this->config->app->facebook->appID."'");

        if(!$page)
            throw new Exception("no page found for fb app id: ".$this->config->app->facebook->appID);

        return $page;
    }

    /**
     * get a facebook openGraph story object
     * @access private
     * @param object $app the app object from config
     * @return string
     */
    private function _getNewFacebookStoryObject($object)
    {
        $data = $this->setStoryData($object);

        $story_object = $this->fbConfig["og_namespace"].":".$this->fbConfig["og_story_object"];

        //new object for JSON encoding
        $obj = new \stdClass();
        $obj->og__type        = $story_object;
        $obj->fb__app_id      = $this->config->app->facebook->appID;
        $obj->og__site_name   = $this->config->app->name;
        $obj->og__title       = $data["title"];
        $obj->og__description = $data["description"];
        $obj->og__url         = $data["url"];
        $obj->og__image       = $data["image"];
        $obj->og__determiner  = isset($data["determiner"]) ? $data["determiner"] : "an";

        //set custom props
        if($obj->og__type == $story_object) {

            //set place caption
            $namespace = $this->fbConfig["og_namespace"]."__place_caption";
            $obj->{$namespace} = $object->eventsPlaces->eplaces->name;
        }

        //encode JSON & replace prefix:data format strings
        $obj = json_encode($obj);
        $obj = str_replace("__", ":", $obj);
        //var_dump($obj);exit;

        return $obj;
    }
}
