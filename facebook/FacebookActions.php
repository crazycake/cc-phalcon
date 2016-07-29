<?php
/**
 * Facebook Actions Trait - Facebook php sdk v5.0
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
//CrazyCake Libs
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Services\Redis;
use CrazyCake\Helpers\Dates;

/**
 * Facebook Actions
 */
trait FacebookActions
{
    /**
     * Set Facebook Story Data
     * @param object $object - The OG object
     */
    abstract public function setStoryData($object);

    /**
     * Config var
     * @var array
     */
    public $facebook_actions_conf;

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

    /**
     * Upload path
     * @var string
     */
    protected $upload_path;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     */
    public function initFacebookActions($conf = [])
    {
        //set confs
        $this->facebook_actions_conf = $conf;
        //set upload path
        $this->upload_path = PUBLIC_PATH."uploads/temp/";

        //upload path
        if (!is_dir($this->upload_path))
            mkdir($this->upload_path, 0755, true);

        //set redis service
        $this->redis = new Redis();

        //set Facebook SDK Object
        if (is_null($this->fb)) {

            $this->fb = new \Facebook\Facebook([
                "app_id"     => $this->config->app->facebook->appID,
                "app_secret" => $this->config->app->facebook->appKey,
                //api version
                "default_graph_version" => "v2.5"
            ]);
        }
    }

    /**
     * Publish Facebook feed action
     * @param object $user_fb - The ORM facebook user
     * @param object $object - The open graph object
     * @param array $payload - The input payload
     * @param int $attempt - Fallback exception, retry
     * @return string - The object ID
     */
    public function publish($user_fb = null, $object = null, $payload = [], $attempt = 0)
    {
        //get user facebook data
        $user_fb_class = AppModule::getClass("user_facebook_page");

        $is_fallback = false;
        $exception   = false;

        //if user dont have a linked FB account.
        if (!is_object($user_fb) || !isset($user_fb->fac))
            $is_fallback = true;

        //for fallback, switch to user page data
        if ($is_fallback)
            $user_fb = $this->_getPageUser();

        try {

            //checkin action only once
            $count = 0;
            if (!$is_fallback) {

                //create a unique day key for user, qr_hash & time
                $key   = sha1($user_fb->id.$payload["action"].date("Y-m-d"));
                $count = $this->redis->get($key);

                //increment action
                $this->redis->set($key, is_null($count) ? 1 : $count+1);
            }

            //actions
            $method = "_".$payload["action"]."Action";
            //reflection
            $object_id = $this->$method($user_fb, $object, $is_fallback, $count+1);
            //log response
            $this->logger->debug("FacebookActions::publishAction -> FB Object created, facebook UserId: $user_fb->id. Payload: ".$payload["action"].". Data: ".json_encode($object_id));
            //var_dump($response, $is_fallback, $object_id);exit;

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
        if ($payload["action"] != "photo")
            throw $exception;

        //if failed more than once, throw exception
        if (empty($attempt))
            $this->publish(null, $object, $payload, 1);
        else
            throw $exception;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Checkin action
     * @param object $user_fb - The ORM facebook user
     * @param object $object - The open graph object
     * @param boolean $is_fallback - The action is a fallback
     * @param int $count - The number of times this action was triggered
     */
    private function _checkinAction($user_fb, $object, $is_fallback = false, $count = 0)
    {
        if ($is_fallback) {

            throw new Exception("Checkin publish action failed (fb error).");
        }
        else if ($this->facebook_actions_conf["publish_day_limit"] && $count > 1) {

            throw new Exception("User reached checkin max post times for today (app restriction)");
        }

        //get event facebook object
        $fb_object = $object->{$this->facebook_actions_conf["object_fb_relation"]};

        if (!$fb_object || empty($fb_object->checkin_text))
            throw new Exception("Facebook Object is not set up (".$this->facebook_actions_conf["object_fb_relation"].").");

        //get place facebook id
        $place_id = !is_null($fb_object->place_id) ? $fb_object->place_id : $this->facebook_actions_conf["og_default_place_id"];

        //set params
        $data = [
            "link"         => $fb_object->checkin_url,
            "message"      => $fb_object->checkin_text,
            "place"        => $place_id,
            "tags"         => $user_fb->id,
            "created_time" => gmdate("Y-m-d\TH:i:s")
        ];
        //print_r($data);exit;

        //set response
        $response = $this->fb->post("me/feed", $data, $user_fb->fac)->getGraphNode();

        return is_object($response) ? $response->getField("id") : null;
    }

    /**
     * Story action
     * TODO: fix start & end date
     * @param object $user_fb - The ORM facebook user
     * @param object $object - The open graph object
     * @param boolean $is_fallback - The action is a fallback
     * @param int $count - The number of times this action was triggered
     */
    private function _storyAction($user_fb, $object, $is_fallback = false, $count = 0)
    {
        if ($is_fallback) {

            throw new Exception("Story publish action failed (fb error).");
        }
        else if ($this->facebook_actions_conf["publish_day_limit"] && $count > 1) {

            throw new Exception("User reached story max post times for today (app restriction)");
        }

        //get event facebook object
        $fb_object = $object->{$this->facebook_actions_conf["object_fb_relation"]};

        if (!$fb_object || empty($fb_object->story_text))
            throw new Exception("Facebook Object is not set up (".$this->facebook_actions_conf["object_fb_relation"].").");

        //get place facebook id
        $place_id = !is_null($fb_object->place_id) ? $fb_object->place_id : $this->facebook_actions_conf["og_default_place_id"];

        //new facebook story object
        $object = $this->_newStoryObject($object);

        $story_object = $this->facebook_actions_conf["og_namespace"].":".$this->facebook_actions_conf["og_story_object"];
        //push open graph object
        $response = $this->fb->post("me/objects/".$story_object,
                             ["object" => $object], $user_fb->fac)
                             ->getGraphNode();
        //get OG object id
        $object_id = is_object($response) ? $response->getField("id") : false;

        if (!$object_id)
            throw new Exception("Invalid facebook open graph: ".(int)$object_id);

        //now post this story
        $data = [
            //object
            $this->facebook_actions_conf["og_story_object"] => $object_id,
            //common props
            "message"              => $fb_object->story_text,
            "place"                => $place_id,
            "fb:explicitly_shared" => true,
            //aditional props
            "no_feed_story" => false,
            //set time to control action verb
            "start_time" => gmdate("Y-m-d\TH:i:s"),    //example "2015-06-18T18:30:30-00:00"
            "end_time"   => (new \DateTime())->modify("+2 day")->format("Y-m-d\TH:i:s") //end time, HARDCODED
        ];
        //print_r($params);exit;
        $action   = $this->facebook_actions_conf["og_namespace"].":".$this->facebook_actions_conf["og_story_action"];
        $response = $this->fb->post("/me/".$action, $data, $user_fb->fac)->getGraphNode();
        //print_r($response);exit;

        return is_object($response) ? $response->getField("id") : null;
    }

    /**
     * Upload a photo
     * @param object $user_fb - The ORM facebook user
     * @param object $object - The open graph object
     * @param boolean $is_fallback - The action is a fallback
     * @param int $count - The number of times this action was triggered
     */
    private function _photoAction($user_fb, $object, $is_fallback = false, $count = 0)
    {
        //get event facebook object
        $fb_object = $object->{$this->facebook_actions_conf["object_fb_relation"]};

        if (!$fb_object || empty($fb_object->photo_text)) {
            throw new Exception("Facebook Object is not set up (".$this->facebook_actions_conf["object_fb_relation"].").");
        }

        //get uploaded files
        $file_path = $this->upload_path;

        //multipart
        if ($this->request->hasFiles()) {

            //get uploaded file
            $file       = current($this->request->getUploadedFiles());
            $file_path .= $file->getName();
            //move file
            $file->moveTo($file_path);
        }
        //base64
        else {

            //get raw file
            $base64_string = $this->request->getPost("raw_file");

            if (!$base64_string)
                throw new Exception("no raw or multipart input file given.");

            $file_name = "social-".$object->namespace."-".uniqid().".jpg";
            $file_path = $this->_base64ToJpg($base64_string, $file_path.$file_name);
        }

        //set action URI
        if ($is_fallback)
            $action_uri = !is_null($fb_object->album_id) ? $fb_object->album_id."/photos" : $user_fb->id."/photos";
        else
            $action_uri = $user_fb->id."/photos";

        // Upload to a user"s profile. The photo will be in the first album in the profile. You can also upload to
        // a specific album by using /ALBUM_ID as the path
        $response  = null;
        $exception = false;

        try {
            $data = [
                "message" => $fb_object->photo_text,
                "source"  => $this->fb->fileToUpload($file_path)
            ];
            //fb request
            $response = $this->fb->post("/$action_uri", $data, $user_fb->fac)->getGraphNode();

            return is_object($response) ? $response->getField("post_id") : null;
        }
        catch (FacebookSDKException $e) { $response = $e; }
        catch (Exception $e)            { $response = $e; }
        catch (\Exception $e)           { $response = $e; }

        //remove temp file
        if (is_file($file_path))
            unlink($file_path);

        throw $response;
    }

    /**
     * Get Facebook Page as Fallback
     */
    private function _getPageUser()
    {
        //get a facebook admin
        if (!class_exists(AppModule::getClass("user_facebook_page")))
            throw new Exception("UserFacebook class not found [user_facebook_page]");

        $fb_pages = AppModule::getClass("user_facebook_page");

        $page = $fb_pages::findFirstByAppId($this->config->app->facebook->appID);

        if (!$page)
            throw new Exception("no page found for fb app id: ".$this->config->app->facebook->appID);

        return $page;
    }

    /**
     * Gets a facebook openGraph story object
     * @access private
     * @param object $object - The open graph object
     * @return string
     */
    private function _newStoryObject($object)
    {
        $data = $this->setStoryData($object);

        $story_object = $this->facebook_actions_conf["og_namespace"].":".$this->facebook_actions_conf["og_story_object"];

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
        if ($obj->og__type == $story_object) {

            //set place caption
            $namespace = $this->facebook_actions_conf["og_namespace"]."__place_caption";
            $obj->{$namespace} = $data["place"];
        }

        //encode JSON & replace prefix:data format strings
        $obj = json_encode($obj);
        $obj = str_replace("__", ":", $obj);
        //var_dump($obj);exit;

        return $obj;
    }

    /**
     * Converts a base64 string to image file. TODO: MOVE THIS to a helper!
     * @param  string $base64_string - The input string
     * @param  string $output_file - The output file
     */
    protected function _base64ToJpg($base64_string = "", $output_file = "")
    {
        $ifp  = fopen($output_file, "wb");
        $data = explode(",", $base64_string);
        $body = isset($data[1]) ? $data[1] : $data[0];

        fwrite($ifp, base64_decode($body));
        fclose($ifp);

        return $output_file;
    }
}
