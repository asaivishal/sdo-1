<?php
/**
 * +-----------------------------------------------------------------------------+
 * | (c) Copyright IBM Corporation 2006, 2007.                                   |
 * | All Rights Reserved.                                                        |
 * +-----------------------------------------------------------------------------+
 * | Licensed under the Apache License, Version 2.0 (the "License"); you may not |
 * | use this file except in compliance with the License. You may obtain a copy  |
 * | of the License at -                                                         |
 * |                                                                             |
 * |                   http://www.apache.org/licenses/LICENSE-2.0                |
 * |                                                                             |
 * | Unless required by applicable law or agreed to in writing, software         |
 * | distributed under the License is distributed on an "AS IS" BASIS, WITHOUT   |
 * | WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.            |
 * | See the License for the specific language governing  permissions and        |
 * | limitations under the License.                                              |
 * +-----------------------------------------------------------------------------+
 * | Author: Graham Charters,                                                    |
 * |         Matthew Peters,                                                     |
 * |         Megan Beynon,                                                       |
 * |         Chris Miller.                                                       |
 * |                                                                             |
 * +-----------------------------------------------------------------------------+
 * $Id: SCA.php 254122 2008-03-03 17:56:38Z mfp $
 *
 * PHP Version 5
 *
 * @category SCA_SDO
 * @package  SCA_SDO
 * @author   Graham Charters <gcc@php.net>
 * @license  Apache http://www.apache.org/licenses/LICENSE-2.0
 * @link     http://www.osoa.org/display/PHP/
 */

require_once "SCA/SCA_Exceptions.php";
require_once "SCA/SCA_AnnotationReader.php";
require_once "SCA/SCA_Helper.php";
require_once "SCA/SCA_LogFactory.php";
require_once "SCA/SCA_BindingFactory.php";
require_once "SCA/SCA_HttpHeaderCatcher.php";

/* TODO remove this once the Tuscany binding is converted to
* the pluggable model
*/
require_once "SCA/Bindings/tuscany/SCA_TuscanyProxy.php";

/**
 * Service Component Architecture class
 *
 * Purpose:
 * To ensure that SCA components are initialised and processed, and requests
 * to them are correctly handled.
 *
 * Public Methods:
 *
 * initComponent()
 * This method is used to determine which of the three reasons why the SCA
 * component calling us has been invoked. These reasons are:
 * 1) a POST request with a SOAP request was made to the component
 * 2) a GET request for WSDL was made to the component
 * 3) the component was simply included as a component to be called locally.
 * If the request is one of 1) or 2) the requests are processed. If the
 * component was included in another SCA Component no additional processing is
 * required.
 *
 * getService()
 * createInstanceAndFillInReferences()
 * constructServiceDescription()
 * generateWSDL()
 * createDataObject()
 *
 * Private Methods:
 *
 * _isSoapRequest()
 * This method is used to determine whether a SOAP POST request was made to
 * the component. Additionally, this method detects whether the request is
 * one that has been passed on to another component.
 *
 * _wsdlRequested()
 * This is used to determine whether WSDL for the component was requested.
 *
 * _handleRequestForWSDL()
 * This method is used to handle the case where WSDL for the component was
 * requested. It echos the WSDL, and caches it locally to a file, so ?wsdl is also
 * the way to refresh the cached copy
 *
 * _handleSoapRequest()
 * This method is used to handle the SOAP request.
 *
 *
 * convertedSoapFault()
 * This method is used to convert a SOAP Fault into the appropriate
 * SCA Exception.
 *
 * @category SCA_SDO
 * @package  SCA_SDO
 * @author   Graham Charters <gcc@php.net>
 * @license  Apache http://www.apache.org/licenses/LICENSE-2.0
 * @link     http://www.osoa.org/display/PHP/
 */
class SCA
{
    const DEBUG = false;
    public static $logger;
    public static $xml_das_array  = array();

    public static $http_header_catcher = null;

    /**
     * Send HTTP header
     *
     * @param string $header HTTP Header to send
     *
     * @return null
     */
    public static function sendHttpHeader($header)
    {
        if (self::$http_header_catcher === null) {
            self::$logger->log("sending http header: $header");
            header($header);
        } else {
            self::$http_header_catcher->catchHeader($header);
        }
    }

    /**
     * Set HTTP Header Catcher
     *
     * @param mixed $catcher Catcher
     *
     * @return null
     */
    public static function setHttpHeaderCatcher($catcher)
    {
        self::$http_header_catcher = $catcher;
    }

    // When set true this flag indicates that SCA is being used
    // as an embedded component of the Tuscany C++ SCA runtime
    // and it affects how references are created on services
    protected static $is_embedded = false;

    /**
     * Set is Embedded
     *
     * @param mixed $is_embedded Is embedded?
     *
     * @return null
     */
    public static function setIsEmbedded($is_embedded)
    {
        self::$is_embedded = $is_embedded;
    }

    /**
     * Initialize component
     *
     * @param mixed $calling_component_filename Filename
     *
     * @return null
     */
    public static function initComponent($calling_component_filename)
    {

        //Create the logging mechanism
        self::$logger = SCA_LogFactory::create();

        // Turn on logging here by removing the comment from the following line
        //             self::$logger->startLog();

        self::$logger->log('Entering');
        self::$logger->log("Called from $calling_component_filename");

        if (isset($_SERVER['HTTP_HOST']))
        self::$logger->log('$_SERVER[\'HTTP_HOST\'] = ' .  $_SERVER['HTTP_HOST']);

        if (isset($_SERVER['REQUEST_METHOD']))
        self::$logger->log('$_SERVER[\'REQUEST_METHOD\'] = ' .  $_SERVER['REQUEST_METHOD']);

        if (isset($_SERVER['CONTENT_TYPE']) )
        self::$logger->log('$_SERVER[\'CONTENT_TYPE\'] = ' .  $_SERVER['CONTENT_TYPE']);

        // contains the X.wsdl in http://..../X.php/X.wsdl
        if (isset($_SERVER['PATH_INFO']))
        self::$logger->log('$_SERVER[\'PATH_INFO\'] = ' .  $_SERVER['PATH_INFO']);

        if (isset($_SERVER['PHP_SELF']))
        self::$logger->log('$_SERVER[\'PHP_SELF\'] = ' .  $_SERVER['PHP_SELF']);

        if (isset($_SERVER['REQUEST_URI']))
        self::$logger->log('$_SERVER[\'REQUEST_URI\'] = ' .  $_SERVER['REQUEST_URI']);

        if (isset($_GET['wsdl']))
        self::$logger->log('$_GET[\'wsdl\'] = ' .  $_GET['wsdl']);

        /**
         * The instance check around the class - if (!class_exists... -
         * makes sure that we get called here once and once only in any instance
         * of php - i.e. by the first non-SCA client to include SCA, or the target
         * component in a web request.
         *
         * There are three different ways we can find ourselves here.
         * 1. We have been included by a non-SCA client script. It is presumably
         *    later going to call getService() and/or createDataObject().
         * 2. We are the target of an HTTP request for WSDL, SMD, etc. i.e. a service file
         * 3. We are the target of a web request of some sort: WS, JSON, etc.
         *
         * How do we distinguish these to do the right thing?
         * 1. Generate a class name from the name of the including file and see
         *    if it exists. If not, then we are in a plain old client script.
         *    If the class does exist but doesn't have @service then it is still
         *    just a plain old client script.
         * 2. This is a request for a service file if we are the target of an
         *    HTTP request and we have the expected ?wsdl, ?smd etc. on the URL
         * 3. Consider this is a web request otherwise, since we have been included
         *
         * We would get caught out if a non-SCA script were simply to
         * to include a component rather than including SCA and using getService
         * to get a proxy to it.
         */

        if (SCA::_includedByAClientScriptThatIsNotAComponent($calling_component_filename)) {
            SCA::$logger->log('included by a client script that is not a component');
            return;
        }

        $service_description = self::constructServiceDescription($calling_component_filename);
        if (isset($_SERVER['HTTP_HOST'] )) {
            $http_host = $_SERVER['HTTP_HOST'];
        } else {
            $http_host = "localhost";
        }
        $service_description->script_name = $_SERVER['SCRIPT_NAME'];
        $service_description->http_host   = $http_host;

        foreach ($service_description->binding as $binding_string) {

            SCA::$logger->log("Applying tests for a $binding_string binding");
            $request_tester = SCA_Binding_Factory::createRequestTester($binding_string);
            if ($request_tester->isServiceDescriptionRequest($calling_component_filename)) {
                SCA::$logger->log("The request is a service description request for $binding_string");
                $service_description_generator = SCA_Binding_Factory::createServiceDescriptionGenerator($binding_string);
                $service_description_generator->generate($service_description);
                SCA::$logger->log('After having generated service description');
                return;
            }
            if ($request_tester->isServiceRequest($calling_component_filename)) {
                SCA::$logger->log("The request is a service request for $binding_string");
                $service_request_handler = SCA_Binding_Factory::createServiceRequestHandler($binding_string);
                $service_request_handler->handle($calling_component_filename, $service_description);
                SCA::$logger->log('After having handled service request');
                return;
            }
        }

        /*
        There are other reasons you can get to here - a component loaded
        locally, for example, or loaded as a result of a SOAP request
        but some other component is the real destination.
        None of them are errors though, so nothing needs to be done.
        */
        self::$logger->log('Request was not ATOM, JSON, SOAP, or a request for a .smd or .wsdl file.');
    }

    /**
     * Included by a client script?
     *
     * @param string $calling_component_filename Filename
     *
     * @return bool
     */
    private static function _includedByAClientScriptThatIsNotAComponent($calling_component_filename)
    {
        $class_name = SCA_Helper::guessClassName($calling_component_filename);
        if (!class_exists($class_name, false)) {
            return true;
        }

        if (class_exists($class_name, false)) {
            $reflection = new ReflectionClass($class_name);
            $reader     = new SCA_CommentReader($reflection->getDocComment());
            if (!$reader->isService()) {
                return true;
            }
        }
        return false;
    }

    /**
     * This is where decisions about what type of service is to be made,
     * are made.
     *
     * @param string $target         the file name - could be a php file, wsdl, json smd etc
     * @param string $type           what sort of binding if ambiguous - could be local, soap, jsonrpc, atom, xmlrpc, ...
     * @param array  $binding_config array of parameters found in the annotations - ebaysoap for example needs
     *
     * @return proxy
     * @throws SCA_RuntimeException if the target is null or empty string
     */
    public static function getService($target, $type = null, $binding_config = null)
    {
        self::$logger->log("Entering");

        $backtrace                 = debug_backtrace();
        $immediate_caller_filename = $backtrace[0]['file'];

        if ($target === null) {
            $msg = "SCA::getService was called from $immediate_caller_filename with a null argument";
            throw new SCA_RuntimeException($msg);
        }

        if (strlen($target) == 0) {
            $msg = "SCA::getService was called from $immediate_caller_filename with an empty argument";
            throw new SCA_RuntimeException($msg);
        }

        self::$logger->log("Target is $target , Type is $type");

        // automatically create a tuscany proxy if SCA is embedded in tuscany C++ SCA
        // there isn't really a sound reason for doing this but the following
        // path manipulation code crashes with php running in embedded mode.
        // needs further investigation
        if (self::$is_embedded) {
            return new SCA_TuscanyProxy($target);
        }

        // set up the type in the case where getService has been
        // called from a client script and the type has defaulted to null

        if ($type == null && (strstr($target, '.php') == '.php') &&  (strstr($target, 'http:') || strstr($target, 'https:'))) {
            throw new SCA_RuntimeException("The target $target appears to be for a remote component, but needs a binding to be specified");
        }

        if ($type == null) {
            if (strstr($target, '.wsdl') == '.wsdl' || strstr($target, '?wsdl') == '?wsdl') { // end with .wsdl or ?wsdl
                SCA::$logger->log("Inferring from presence of .wsdl or ?wsdl that a soap proxy is required for this target.");
                $type = 'soap';
            } else if (strstr($target, '.smd') == '.smd' || strstr($target, '?smd') == '?smd') {
                SCA::$logger->log("Inferring from presence of .smd or ?smd that a jsonrpc proxy is required for this target.");
                $type = 'jsonrpc';
            } else if (strstr($target, '.php') == '.php') { // .php on the end
                SCA::$logger->log("Inferring from the fact that the target ends in .php that a local proxy is required for this target.");
                $type = 'local';
            }
        }

        if (!isset($type) || $type == null) {
            $msg = "The right binding to use could not be inferred from the target {$target}. The binding must be specified as the second argument to SCA::getService().";
            throw new SCA_RuntimeException($msg);
        }

        /**
         * Calculate the directory against which we will resolve relative paths
         * If getService has been called directly from a client script,
         * this is the immediate caller, just one step back up the call stack
         */
        $base_path_for_relative_paths = dirname($immediate_caller_filename);

        SCA::$logger->log("About to create a $type proxy for target $target. Base path for relative paths is $base_path_for_relative_paths");

        $proxy = SCA_Binding_Factory::createProxy(
            $type,
            $target,
            $base_path_for_relative_paths,
            $binding_config
        );

        self::$logger->log("Exiting");
        return $proxy;
    }

    /**
     * the following method has not been kept up to date with recent changes to the
     * way fill in references works, and there are no unit tests to keep it running
     * Allow it to molder here for a while.
     */

    /**
     * THE OLD VERSION OF createInstanceAndFillInReferences(). INCLUDES
     * FUNCTIONALITY REQUIRED WHEN SCA IS RUNNING EMBEDDED IN TUSCANY SCA C++
     *
     * Instantiate the component, examine the annotations, find the dependencies,
     * call getService to create a proxy for each one, and assign to the
     * instance variables. The call(s) to getService may recurse back through here
     * if those dependencies also have dependencies
     *
     * @param string $class_name name of the class
     *
     * @return class instance
     */
    public static function createInstanceAndFillInReferences($class_name)
    {
        self::$logger->log("Entering");
        self::$logger->log("Class name of component to instantiate is $class_name");
        $instance   = new $class_name;
        $reader     = new SCA_AnnotationReader($instance);
        $references = $reader->reflectReferencesFull($instance);
        self::$logger->log("There are " . count($references) . " references to be filled in");
        $reflection = new ReflectionObject($instance);
        foreach ($references as $ref_name => $ref_type) {
            $ref_value = $ref_type->getBinding();
            self::$logger->log("Reference name = $ref_name, binding = $ref_value");
            $reference_proxy = null;
            if (self::$is_embedded) {
                $reference_proxy = new SCA_TuscanyProxy($ref_value);
            } else {
                if (SCA_Helper::isARelativePath($ref_value)) {
                    $ref_value = SCA_Helper::constructAbsolutePath(
                        $ref_value,
                        $class_name
                    );
                }
                $reference_proxy = SCA::getService($ref_value);
            }

            $prop = $reflection->getProperty($ref_name);

            // add the reference information to the proxy
            // this is added just in case there are any
            // extra types specified in the doc comment
            // for this reference
            $ref_type->addClassName($class_name);
            $reference_proxy->addReferenceType($ref_type);

            $prop->setValue($instance, $reference_proxy); // NB recursion here
        }

        self::$logger->log("Exiting");
        return $instance;
    }

    /**
     * Instantiate the component
     *
     * @param string $class_name class to instantiate
     *
     * @return object instance of the class
     */
    public static function createInstance($class_name)
    {
        self::$logger->log("Entering");
        self::$logger->log("Class name of component to instantiate is $class_name");
        $instance = new $class_name;
        return      $instance;
    }

    /**
     * Examine the annotations, find the dependencies,
     * create a proxy for each one, and assign to the
     * instance variables. The call(s) to getService may recurse back through here
     * if those dependencies also have dependencies
     *
     * @param object $instance instance of a class which may or may not contain references to other components
     *
     * @return object instance
     */
    public static function fillInReferences($instance)
    {
        self::$logger->log("Entering");

        $class_name = get_class($instance);
        $file       = SCA_Helper::getFileContainingClass($class_name);

        $path_to_resolve_relative_paths_against = dirname($file);
        self::$logger->log("Path to resolve relative paths against is $path_to_resolve_relative_paths_against");

        $reader     = new SCA_AnnotationReader($instance);
        $references = $reader->reflectReferencesFull();
        self::$logger->log("Number of references to be filled in: ".count($references));
        $reflection = new ReflectionObject($instance);

        foreach ($references as $ref_name => $ref_type) {
            //                self::$logger->log("Reference name = $ref_name, ref_type = " . print_r($ref_type,true));
            self::$logger->log("Reference name = $ref_name");

            $ref_value       = $ref_type->getBinding();
            $prop            = $reflection->getProperty($ref_name);
            $reference_proxy = SCA_Binding_Factory::createProxy(
                $ref_type->getBindingType(),
                $ref_value,
                $path_to_resolve_relative_paths_against,
                $ref_type->getBindingConfig()
            ); // NB recursion here


            // add the reference information to the proxy
            // this is added just in case there are any
            // extra types specified in the doc comment
            // for this reference
            $ref_type->addClassName(get_class($instance));
            $reference_proxy->addReferenceType($ref_type);

            $prop->setValue($instance, $reference_proxy);
        }

        self::$logger->log("Exiting");
    }

    /**
     * Create an array containing the service descriptions from the annotations
     * found in the class file.
     *
     * @param string $class_file Class file containing the service annotations
     *
     * @return object The service description object
     * @throws SCA_RuntimeException ... when things go wrong
     */
    public static function constructServiceDescription($class_file)
    {
        $class_name = SCA_Helper::guessClassName($class_file);

        if (!class_exists($class_name, false)) {
            // The code analyzer marks the following include with a variable name as
            // unsafe. It is safe, however as the class file name can only come from
            // a getService call or an annotation.
            include "$class_file";
        }

        if (class_exists($class_name, false)) {
            $instance            = new $class_name;
            $reader              = new SCA_AnnotationReader($instance);
            $service_description = $reader->reflectService();

            $service_description->class_name      = $class_name;
            $service_description->realpath        = realpath($class_file);
            $service_description->targetnamespace = "http://$class_name";

        } else {
            throw new SCA_RuntimeException("Invalid Classname: $class_name");
        }

        return $service_description;
    }


    /**
     * This function can be called directly by a component to
     * create a dataobject from the namespaces defined in the @types annotations.
     *
     * @param string $namespace_uri Namespace identifying the xsd
     * @param string $type_name     Element being reference in the xsd
     *
     * @return object                Empty Data Object structure
     */
    public static function createDataObject($namespace_uri, $type_name)
    {
        // Find out who/what called this function so that the type annotations
        // that define the xml used to create a 'das' can be scanned.
        $backtrace = debug_backtrace();
        $caller    = $backtrace[0];
        $filepath  = $caller['file'];
        $keyname   = md5(serialize($filepath));

        // Check if there is a matching xsd in the xmldas array
        if (array_key_exists($keyname, self::$xml_das_array)) {
            $xmldas = self::$xml_das_array[$keyname];
        } else {
            // The trap will only trigger if the Annotations cannot be found
            // normally this is because a SCA Client Component has incorrectly
            // attempted to use this method, rather than the 'createDataObject'
            // method of either the 'Proxy, or LocalProxy.
            try {
                $class_name = SCA_Helper::guessClassName($filepath);
                $xmldas     = SCA_Helper::getXmldas($class_name, null);
                self::$xml_das_array[$keyname] = $xmldas;
            } catch( ReflectionException $e) {
                $msg =  $e->getMessage();
                throw new SCA_RuntimeException(
                    "A PHP ReflectionException was thrown with message $msg. "
                    . "This is usually the result of calling SCA::createDataObject from a user script. "
                    . "User scripts should only call createDataObject on an SCA Proxy object."
                );
            }
        }

        return $xmldas->createDataObject($namespace_uri, $type_name);

    }

    /**
     * This function can be called directly by a php script to dispatch a request to
     * an SCA service. You only need to use this operation when you organize your
     * code so that the service implementation (and SCA/SCA.php include) are included
     * by in a script that is just a wrapper script and is not acting as a client for
     * the service. This can happen if you want keep php files that define service
     * outside of the htdocs directory
     *
     * @param string $class_name The class_name that implements the target service
     *
     * @return void
     */
    public static function dispatch($class_name)
    {
        $file_name                  = SCA_Helper::getFileContainingClass($class_name);
        $_SERVER['SCRIPT_FILENAME'] = $file_name;
        SCA::initComponent($file_name);
    }
}


/**
* Check that the correct extensions have been loaded before starting, and
* initialise the sca class to the script file including SCA.
*/
SCA_Helper::checkSdoExtensionLoaded();

$backtrace        = debug_backtrace();
$immediate_caller = $backtrace[0]['file'];

SCA::initComponent($immediate_caller);

