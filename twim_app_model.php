<?php

/**
 * Twim Base Model
 *
 * PHP versions 5
 *
 * Copyright 2011, nojimage (http://php-tips.com/)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @version   1.0
 * @author    nojimage <nojimage at gmail.com>
 * @copyright 2011 nojimage (http://php-tips.com/)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   twim
 * @since     File available since Release 1.0
 * @original  https://github.com/neilcrookes/CakePHP-Twitter-API-Plugin
 * */
/*
 * @property TwimSource $Source
 */
class TwimAppModel extends AppModel {

    /**
     * The datasource all models in the plugin use.
     *
     * @var string
     */
    public $useDbConfig = 'twitter';

    /**
     * The models in the plugin get data from the web service, so they don't need
     * a table.
     *
     * @var string
     */
    public $useTable = false;

    /**
     * Methods in the models result in HTTP requests using the HttpSocket. So
     * rather than do all the heavy lifting in the datasource, we set the various
     * params of the request in the individual model methods. This ties the model
     * to the data layer, but these models are especially for this datasource.
     *
     * @var array
     */
    public $request = array();

    /**
     * responese body
     *
     * @var array
     */
    public $response = null;

    /**
     * Twitter API url base
     *
     * @var string
     */
    public $apiUrlBase = '';

    /**
     * The custom find types that require authentication
     *
     * @var array
     */
    public $findMethodsRequiringAuth = array();

    /**
     * The options allowed by each of the custom find types
     * 
     * @var array
     */
    public $allowedFindOptions = array();

    public function __get($name) {
        $model = ClassRegistry::init('Twim.Twim' . $name);
        return $model;
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }

    /**
     * Adds the datasource to the connection manager if it's not already there,
     * which it won't be if you've not added it to your app/config/database.php
     * file.
     *
     * @param $id
     * @param $table
     * @param $ds
     */
    public function __construct($id = false, $table = null, $ds = null) {

        $sources = ConnectionManager::sourceList();

        if (!in_array('twitter', $sources)) {
            ConnectionManager::create('twitter', array('datasource' => 'Twim.TwimSource'));
        }

        parent::__construct($id, $table, $ds);
    }

    /**
     *
     * @return TwimSource
     */
    public function getDataSource() {
        return ConnectionManager::getDataSource($this->useDbConfig);
    }

    public function setDataSource($dataSource) {
        parent::setDataSource($dataSource);
        return $this;
    }

    public function setDataSourceConfig($config = array()) {
        $ds = $this->getDataSource($this->useDbConfig);
        if (!is_array($ds->config)) {
            $ds->config = array($ds->config);
        }
        $ds->config = array_merge($ds->config, $config);
        return $this;
    }

    public function onError() {

        parent::onError();

        // == throw Expection
        if ($this->getDataSource()->config['throw_exception']) {
            $message = $this->getDataSource()->Http->response['body'];

            if (is_array($this->response) && !empty($this->response['error'])) {
                $message = $this->response['error'];
            } else if (is_array($this->response) && !empty($this->response['errors'][0]['message'])) {
                $message = $this->response['errors'][0]['message'];
            } else if ($this->getDataSource()->Http->response['status']['code'] == 503) {
                $message = __d('twim', 'Twitter is over capacity.', true);
            } else if ($this->getDataSource()->Http->response['status']['code'] == 404) {
                $message = sprintf('The requested URL %s was not found.', $this->getDataSource()->Http->url($this->getDataSource()->Http->request['uri']));
            }

            throw new RuntimeException(
                    $message,
                    $this->getDataSource()->Http->response['status']['code']);
        }
    }

    /**
     * filter options and setup request
     *
     * @param type $type
     * @param array $options
     */
    protected function _setupRequest($type, array $options) {

        $this->request['uri']['path'] = $this->apiUrlBase . Inflector::underscore($type);

        if (isset($this->allowedFindOptions[$type])) {
            $this->request['uri']['query'] = array_intersect_key($options, array_flip($this->allowedFindOptions[$type]));
        }

        if (in_array($type, $this->findMethodsRequiringAuth)) {
            $this->request['auth'] = true;
        }

        return $this;
    }

}
