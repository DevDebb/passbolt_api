<?php
/**
 * User Model
 *
 * Copyright 2012, Passbolt
 * Passbolt(tm), the simple password management solution 
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2012, Passbolt.com
 * @package       app.Model.user
 * @since         version 2.12.7
 * @license       http://www.passbolt.com/license
 */
App::uses('AuthComponent', 'Controller/Component');
App::uses('Role', 'Model');
class User extends AppModel {
  public $name = 'User';
  public $belongsTo = array('Role');

  /**
   * They are legions
   */
  const Anonymous = 'Anonymous';

  /**
   * Constructor
   * @link http://api20.cakephp.org/class/app-model#method-AppModel__construct
   */
  public function __construct($id = false, $table = null, $ds = null) {
    parent::__construct($id, $table, $ds);
    $this->setValidationRules();
  }

  /**
   * Set the validation rules upon context
   * @param string context
   */
  function setValidationRules($context='default') {
    $this->validate = User::getValidationRules($context);
  }

  /**
   * Get the validation rules upon context
   * @param string context
   */
  static function getValidationRules($context='default') {
    $default = array(
      'username' => array(
        'required' => array(
          'required' => true,
          'allowEmpty' => false,
          'rule' => array('notEmpty'),
          'message' => __('A username is required')
        ),
        'email' => array(
          'rule' => array('email'),
          'message' => __('The username should be a valid email address')
        )
      ),
      'password' => array(
        'required' => array(
          'required' => true,
          'allowEmpty' => false,
          'rule' => array('notEmpty'),
          'message' => __('A password is required')
        ),
        'minLength' => array(
          'rule' => array('minLength',5),
          'message' => __('Your password should be at least composed of 5 characters')
        )
      )
    );
    switch ($context) {
      case 'default' :
        $rules = $default; 
      break;
    }
    return $rules;
  }

  /**
   * Before Save callback
   * @link http://api20.cakephp.org/class/app-model#method-AppModel__construct
   * @return bool, if true proceed with save
   */
  public function beforeSave() {
    // encrypt the password
    // @todo use bcrypt instead of md5 hashing
    if (isset($this->data[$this->alias]['password'])) {
      $this->data[$this->alias]['password'] = AuthComponent::password($this->data[$this->alias]['password']);
    }
    return true;
  }

  /**
   * Get the current user
   * @return array the current user or an anonymous user, false if error
   */
  public static function get() {
    Common::getModel('Role');
    $user = AuthComponent::user();
    // if the user is not in Session use a anonymous
    if($user == null) {
      $user = User::setActive(User::Anonymous);
    }
    return $user;
  }

  /**
   * Set the user as currentm
   * It always perform a search on id to avoid abuse (such as using a crafted/fake user)
   * @param mixed UUID, User::Anonymous, or user array with id specified
   * @return array the desired user or an anonymous user, false if error in find
   */
  static function setActive($user = null) {
    // Instantiate the mode are we are in a static/singleton context
    $_this = Common::getModel('User');
    $u = array();
  
    // If user is unspecified or anonymous is requested
    if ($user == null || $user == User::Anonymous) {
      $u = $_this->find('first', User::getFindOptions(User::Anonymous));
    } else {
      // if the user is specified and have a valid ID find it
      if (is_string($user) && Common::isUuid($user)) { 
        $user = array('User' => array('id' => $user));
      }
      $u = $_this->find('first', User::getFindOptions('userActivation',$user) );
    }

    if (empty($u)) {
      return false;
    }

    // Store current user data in session
    App::import('Model', 'CakeSession'); 
    $Session = new CakeSession(); 
    $Session->renew();
    $Session->write(AuthComponent::$sessionKey, $u);

    return $u;
  }

  /**
   * Check if user is admin role - Shortcut Method
   * @return bool true if role is admin
   * @access public
   */ 
  public static function isAdmin() {
    Common::getModel('Role');
    $user = User::get();
    return $user['Role']['name'] == Role::Admin;
  }

  /**
   * Check if user is a guest - Shortcut Method
   * @return bool true if role is guest
   * @access public
   */ 
  public static function isGuest() {
    Common::getModel('Role');
    $user = User::get();
    return $user['Role']['name'] == Role::Guest;
  }

  /**
   * Return the find options to be used
   * @param string context
   * @return array
   */
  static function getFindOptions($case,&$data = null) {
    return array_merge(
      User::getFindConditions($case,&$data),
      User::getFindFields($case)
    );
  }

  /**
   * Return the conditions to be used for a given context
   * for example if you want to activate a User session
   * @param $context string{guest or id}
   * @param $data used in find conditions (such as User.id)
   * @return $condition array
   */
  static function getFindConditions($case = 'guestActivation', &$data = null) {
    $conditions = array();
    switch ($case) { /*
      case 'login':
        $conditions = array(
         'conditions' => array(
           'User.password' => $data['User']['password'],
           'User.username' => $data['User']['username']
         )
       );
      break;
      case 'forgotPassword':
        $conditions = array(
         'conditions' => array(
           'User.username' => $data['User']['username']
         )
        );
      break;
      case 'resetPassword': */
      case 'userActivation':
        $conditions = array(
          'conditions' => array(
            'User.id' => $data['User']['id']
            //'User.active' => 1,
          )
        );
      break;
      case User::Anonymous:
      default:
        $conditions = array(
          'conditions' => array(
            'User.username' => User::Anonymous
          )
        );
      break;
      default:
        throw new exception('ERROR: User::GetFindConditions case undefined');
        //$fields = array();
      break;
    }
    return $conditions;
  }

  /**
   * Return the list of field to fetch for given context
   * @param string $case context ex: login, activation
   * @return $condition array
   */
  static function getFindFields($case = 'guestActivation'){
    switch($case){ /*
      case 'resetPassword':
      case 'forgotPassword':*/
      case User::Anonymous:
        $fields = array(
          'fields' => array(
            'User.id', 'User.username', 'User.role_id'
            //, 'User.active'
          ),
          'contain' => array(
            'Role(id,name)'
          )
        );
      break;
      /* case 'login': */
      case 'userActivation':
        $fields = array(
          'contain' => array(
            'Role(id,name)',
            //'Timezone(id,name)',
            //'Language(id,name,ISO_639-2-alpha2,ISO_639-2-alpha1)',
            //'Settings(*)',
            //'Person(id,firstname,lastname)'
            //'Office(name,acronym,region,type)',
          ),
          'fields' => array(
            'User.id', 'User.username'
            //'User.active','User.permissions',
          )
        );
      break;
      default:
        throw new exception('ERROR: User::GetFindFields case undefined');
      break;
    }
    return $fields;
  }

}