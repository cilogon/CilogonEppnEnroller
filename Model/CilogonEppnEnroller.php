<?php
/**
 * COmanage Registry Cilogon Eppn Enroller Model
 *
 * @package       registry-plugin
 * @version       1.0
 */

class CilogonEppnEnroller extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "enroller";

  // Document foreign keys
  public $cmPluginHasMany = array();

  // Add behaviors
  public $actsAs = array('Containable', 'Changelog' => array('priority' => 5));

  // Association rules from this model to other models
  public $belongsTo = array("CoEnrollmentFlowWedge");

  // Validation rules for table elements
  public $validate = array(
    'co_enrollment_flow_wedge_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    )
  );

}
