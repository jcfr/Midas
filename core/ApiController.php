<?php
/*=========================================================================
 Midas Server
 Copyright Kitware SAS, 26 rue Louis Guérin, 69100 Villeurbanne, France.
 All rights reserved.
 For more information visit http://www.kitware.com/.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

/** API controller base class. */
class ApiController extends REST_Controller
{
    /** @var array */
    private $httpSuccessCode = array(
        'index' => 200, // 200 OK
        'get' => 200,
        'post' => 201, // 201 Created
        'put' => 200,
        'delete' => 200,
    );

    /** Initialize the API controller. */
    public function init()
    {
        $this->disableLayout();
        $this->disableView();
        $this->_response->setBody(null);
        $this->_response->setHttpResponseCode(200); // 200 OK
    }

    /**
     * Return the user DAO.
     *
     * @param array $args parameters from the HTTP request
     * @return UserDao user DAO
     */
    protected function _getUser($args)
    {
        /** @var AuthenticationComponent $authComponent */
        $authComponent = MidasLoader::loadComponent('Authentication');

        return $authComponent->getUser($args, $this->userSession->Dao);
    }

    /**
     * Convert a Midas Server internal error code to standard HTTP status code.
     *
     * @param Exception $exception
     * @return array error message and HTTP code
     */
    protected function _exceptionHandler(Exception $exception)
    {
        $errorInfo['code'] = $exception->getCode();
        $errorInfo['msg'] = $exception->getMessage();
        switch ($errorInfo['code']) {
            case MIDAS_INVALID_PARAMETER:
            case MIDAS_INVALID_POLICY:
            case MIDAS_INTERNAL_ERROR:
            case MIDAS_HTTP_ERROR:
            case MIDAS_UPLOAD_FAILED:
            case MIDAS_UPLOAD_TOKEN_GENERATION_FAILED:
            case MIDAS_SOURCE_OPEN_FAILED:
            case MIDAS_OUTPUT_OPEN_FAILED:
                $httpCode = 400; // 400 Bad Request
                break;
            case MIDAS_INVALID_TOKEN:
            case MIDAS_INVALID_UPLOAD_TOKEN:
                $httpCode = 401; // 401 Unauthorized
                break;
            case MIDAS_NOT_FOUND:
                $httpCode = 404;
                break;
            default:
                $httpCode = 400; // 400 Bad Request
        }

        return array($errorInfo, $httpCode);
    }

    /**
     * Generic wrapper function called by RESTful actions. With the given
     * arguments, it calls the related function in the corresponding
     * ApiComponent and then fills the HTTP status code and results in the
     * response.
     * @param array $args parameters from the HTTP request
     * @param string $resource name of the resource
     * @param string $restAction RESTful actions: get, index, post, put or delete
     * @param array $apiFunctions An array of method name in RESTful action
     *   => function name in the corresponding ApiComponent. This array must have
     *   'default' in its keys.
     * @param null|string $moduleName module from which to get the ApiComponent
     * @param bool $rpcStyle wrap data in RPC-style "data" and "msg" fields
     *
     * {@example * _genericAction(array('id' => 2), 'get', $apiFunctionArray) is called and
     * $apiFunctionArray is $apiFunctions = array('default' => 'itemMove',
     *                                            'move' => 'itemMove',
     *                                            'duplicate' => 'itemDuplicate');
     * for 'curl -v {base_path}/rest/item/move/2' command, Midas Server will call
     * 'itemMove' in the ApiComponent (in core module) to do the API work.
     * for given url {base_path}/rest/item/2, Midas Server will call 'itemMove'
     * in the ApiComponent (in core module) to do the API work.
     * for given url {base_path}/rest/item/duplicate/2, Midas Server will call
     * 'itemDuplicate' in ApiComponent (in core module) to do the API work.
     * }
     */
    protected function _genericAction($args, $resource, $restAction, $apiFunctions, $moduleName = null, $rpcStyle = true)
    {
        $ApiComponent = MidasLoader::loadComponent('Api'.$resource, $moduleName);
        $httpCode = $this->httpSuccessCode[strtolower($restAction)];
        $calledFunction = $apiFunctions['default'];
        $apiResults = array();
        try {
            $userDao = $this->_getUser($args);
            if (isset($args['method'])) {
                $method = strtolower($args['method']);
                if (array_key_exists($method, $apiFunctions)) {
                    $calledFunction = $apiFunctions[$method];
                } else {
                    throw new Exception('Server error. Operation '.$args['method'].' is not supported.', -100);
                }
            }
            if (method_exists($ApiComponent, $calledFunction.'Wrapper')) {
                $calledFunction = $calledFunction.'Wrapper';
            }
            $resultsArray = $ApiComponent->$calledFunction($args, $userDao);
            if ($rpcStyle) {
                if (isset($resultsArray)) {
                    $apiResults['data'] = $resultsArray;
                } else { // if the api function doesn't provide an return value
                    $apiResults['msg'] = 'succeed!'; // there is no exception if code reaches here
                }
            } else {
                $apiResults = $resultsArray;
            }
        } catch (Exception $e) {
            list($apiResults['error'], $httpCode) = $this->_exceptionHandler($e);
        }
        $this->_response->setHttpResponseCode($httpCode);
        // only the data assigned to '$this->view->apiresults' will be serialized
        // in requested format (json, xml, etc) and filled in response body
        $this->view->apiresults = $apiResults;
    }

    /**
     * The index action handles index/list requests; it should respond with a
     * list of the requested resources.
     */
    public function indexAction()
    {
        $this->_response->setHttpResponseCode(405); // 405 Method Not Allowed
    }

    /**
     * The head action handles HEAD requests; it should respond with an
     * identical response to the one that would correspond to a GET request,
     * but without the response body.
     */
    public function headAction()
    {
        $this->_response->setHttpResponseCode(405); // 405 Method Not Allowed
    }

    /**
     * The get action handles GET requests and receives an 'id' parameter; it
     * should respond with the server resource state of the resource identified
     * by the 'id' value.
     */
    public function getAction()
    {
        $this->_response->setHttpResponseCode(405); // 405 Method Not Allowed
    }

    /**
     * The post action handles POST requests; it should accept and digest a
     * POSTed resource representation and persist the resource state.
     */
    public function postAction()
    {
        $this->_response->setHttpResponseCode(405); // 405 Method Not Allowed
    }

    /**
     * The put action handles PUT requests and receives an 'id' parameter; it
     * should update the server resource state of the resource identified by
     * the 'id' value.
     */
    public function putAction()
    {
        $this->_response->setHttpResponseCode(405); // 405 Method Not Allowed
    }

    /**
     * The delete action handles DELETE requests and receives an 'id'
     * parameter; it should update the server resource state of the resource
     * identified by the 'id' value.
     */
    public function deleteAction()
    {
        $this->_response->setHttpResponseCode(405); // 405 Method Not Allowed
    }

    /**
     * The options action handles OPTIONS requests; it should respond with
     * the HTTP methods that the server supports for specified URL.
     */
    public function optionsAction()
    {
        $this->_response->setHeader('Allow', 'OPTIONS');
    }
}
