<?php
/**
 * Core Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

/**
 * Handles public actions for error pages
 * Requires a Frontend or Backend Module
 */
trait Core
{
    /**
     * Logs database query & statements with phalcon event manager
     * @param string $logFile - The log file name
     */
    protected function dblog($logFile = "db.log")
    {
        //Listen all the database events
        $manager = new \Phalcon\Events\Manager();
        $logger  = new \Phalcon\Logger\Adapter\File(APP_PATH."logs/".$logFile);

        $manager->attach('db', function ($event, $connection) use ($logger) {
            //log SQL
            if ($event->getType() == 'beforeQuery')
                $logger->debug("Core:dblog -> SQL:\n".$connection->getSQLStatement());
        });
        // Assign the eventsManager to the db adapter instance
        $this->db->setEventsManager($manager);
    }

    /**
     * Dump a phalcon object for debugging
     * For printing uses Kint library if available
     * @param object $object - Any object
     * @param boolean $exit - Flag for exit script execution
     * @return mixed
     */
    protected function dump($object, $exit = true)
    {
        $object = (new \Phalcon\Debug\Dump())->toJson($object);

        //print output
        class_exists("\\Kint") ? s($object) : print_r($object);

        if ($exit) exit;

        return $object;
    }
}
