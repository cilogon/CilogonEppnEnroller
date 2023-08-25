<?php
/**
 * COmanage Registry Cilogon Eppn Enroller
 *
 * @since         COmanage Registry 1.0.5
 * @version       1.0
 */

App::uses('CoPetitionsController', 'Controller');

class CilogonEppnEnrollerCoPetitionsController extends CoPetitionsController {
  // Class name, used by Cake
  public $name = "CilogonEppnEnrollerCoPetitions";
  public $uses = array(
    'CoPetition',
    'Identifier');


  // Called during an enrollment flow when the identifier used by the
  // enrollee to authenticate, ie. $REMOTE_USER, is attached to the
  // OrgIdentity created by the petition.
  //
  // For Cilogon $REMOTE_USER holds the OIDC sub claim and we need to
  // additionally harvest ePPN.
  protected function execute_plugin_collectIdentifier($id, $onFinish) {
    $logPrefix = "CilogonEppnEnrollerCoPetitionsController execute_plugin_collectIdentifier ";
    $errorFlashText = "There was an error processing the enrollment petition.";

    // Use the petition id to find the petition.
    $args = array();
    $args['conditions']['CoPetition.id'] = $id;
    $args['contain'] = false;
    $coPetition = $this->CoPetition->find('first', $args);
    if (empty($coPetition)) {
      $this->log($logPrefix . "Could not find petition with id $id");
      $this->Flash->set($errorFlashText, array('key' => 'error'));
      $this->redirect("/auth/logout");
      return;
    }

    // Use the petition to find the CoPerson Id.
    if (isset($coPetition['CoPetition']['enrollee_co_person_id'])) {
      $coPersonId = $coPetition['CoPetition']['enrollee_co_person_id'];
    } else {
      $this->log($logPrefix . "Could not find CoPerson from petition with id $id");
      $this->Flash->set($errorFlashText, array('key' => 'error'));
      $this->redirect("/auth/logout");
      return;
    }

    // Find the eppn from the environment.
    $eppn = env('REDIRECT_OIDC_CLAIM_eppn');

    // When Google is the IdP we need to construct the eppn
    // from the REDIRECT_OIDC_CLAIM_oidc value. This construction is
    // deployer specific at this time, so we only do it if
    // we find the right environment variable signaling us 
    // to do it.
    if (empty($eppn)
        && (env('REDIRECT_OIDC_CLAIM_idp') == 'http://google.com/accounts/o8/id')
        && (!empty(env('REDIRECT_OIDC_CLAIM_oidc')))
        && (!empty(env('GW_ASTRONOMY_GOOGLE_EPPN')))) {
        $oidc = env('REDIRECT_OIDC_CLAIM_oidc');
        $eppn = $oidc . '@google.com';
    }

    // When ORCID is the IdP we need to construct the eppn
    // from the REDIRECT_OIDC_CLAIM_oidc value. This construction is
    // deployer specific at this time, so we only do it if
    // we find the right environment variable signaling us 
    // to do it and containing the scope to use.
    if (empty($eppn)
        && (env('REDIRECT_OIDC_CLAIM_idp') == 'http://orcid.org/oauth/authorize')
        && (!empty(env('REDIRECT_OIDC_CLAIM_oidc')))
        && (!empty(env('GW_ASTRONOMY_ORCID_EPPN_SCOPE')))) {
        $oidc = env('REDIRECT_OIDC_CLAIM_oidc');
        $matches = array();
        preg_match('@^http://orcid\.org/(.*)@', $oidc, $matches);
        if(count($matches) == 2) {
          $orcid = trim($matches[1]);
          $eppn = $orcid . '@' . env('GW_ASTRONOMY_ORCID_EPPN_SCOPE');
        }
    }
    
    if (empty($eppn)) {
      $this->log($logPrefix . "Could not find eppn from environment for person with CoPerson Id $coPersonId");
      $this->log($logPrefix . "Environment is " . print_r($_ENV, true));

      // Not all IdPs will assert eppn, so log the issue but allow enrollment to continue on.
      $this->redirect($onFinish);
      return;
    }

    // Find any existing identifier with the eppn value for the CoPerson if any.
    $args = array();
    $args['conditions']['Identifier.co_person_id'] = $coPersonId;
    $args['conditions']['Identifier.type'] = IdentifierEnum::ePPN;
    $args['conditions']['Identifier.status'] = StatusEnum::Active;
    $args['conditions']['Identifier.identifier'] = $eppn;

    $existingIdentifier = $this->Identifier->find('first', $args);
    if (!empty($existingIdentifier)) {
      $this->log($logPrefix . "Identifier $eppn already set for ePPN for CoPerson $coPersonId");
      $this->redirect($onFinish);
      return;
    }

    // Create a new identifier of type eppn with value from eppn.
    $newEppn = array();
    $newEppn['Identifier']['identifier'] = $eppn;
    $newEppn['Identifier']['type'] = IdentifierEnum::ePPN;
    $newEppn['Identifier']['login'] = false;
    $newEppn['Identifier']['status'] = StatusEnum::Active;
    $newEppn['Identifier']['co_person_id'] = $coPersonId;

    if(!($this->Identifier->save($newEppn))) {
      $this->log($logPrefix . "Error saving eppn $eppn for CoPerson with Id $coPersonId");
      $this->Flash->set(_txt('er.eppnenroller.collectidentifier'), array('key' => 'error'));
      $this->Flash->set($errorFlashText, array('key' => 'error'));
      $this->redirect("/auth/logout");
      return;
    }

    $this->log($logPrefix . "Saved eppn " . print_r($newEppn, true));

    $this->redirect($onFinish);
  }
}
