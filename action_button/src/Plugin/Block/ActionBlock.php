<?php

namespace Drupal\action_button\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Provides a 'Action' Block.
 *
 * @Block(
 *   id = "action_block",
 *   admin_label = @Translation("Action block"),
 *   category = @Translation("Custom"),
 * )
 */

class ActionBlock extends BlockBase implements BlockPluginInterface {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $system_number = explode('/', \Drupal::service('path_alias.manager')->getAliasByPath(\Drupal::service('path.current')->getPath()))[2];

    if($system_number == 'structure') {
      $system_number = explode('/', \Drupal::service('path_alias.manager')->getAliasByPath(\Drupal::service('path.current')->getPath()))[7];

    }
    $transfer_options = [
      'query' => ['system_number' => $system_number],
      'attributes' => ['class' => ['btn', 'btn-mini', 'bb-grey', 'baby-blue-hover']],
    ];

    $decommission_options = [
      'query' => ['system_number' => $system_number],
      'attributes' => [
        'class' => ['use-ajax', 'btn', 'btn-mini', 'bb-grey', 'baby-blue-hover'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 700,
          'height' => 400,
        ]),
      ],

    ];

    $edit_options = [
      'attributes' => [
        'class' => ['use-ajax', 'btn', 'btn-mini', 'bb-grey', 'baby-blue-hover'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'target' => 'ajax-test-dialog-wrapper-1',
          'width' => 1000,
        ]),
      ],
    ];

    $user = User::load(\Drupal::currentUser()->id());
    $links = [];

    /** @var object $user */
    if($this->flowPermission($user, "decommission") === true){
      $links[] = Link::fromTextAndUrl(t('Decommission'), Url::fromUri('internal:/form/decommission', $decommission_options))->toRenderable();
    }

    /** @var object $user */
    if($this->flowPermission($user, "transfer") === true){
      $links[] = Link::fromTextAndUrl(t('Transfer'), Url::fromUri('internal:/form/transfer', $transfer_options))->toRenderable();
    }

//    $url = Url::fromUri(
//      "internal:/taskconsole/coassign/task/$system_number",
//      ['#attributes' => [
//        'class' => ['btn', 'btn-default', 'use-ajax'],
//        'data-dialog-type' => 'dialog',
//        'data-dialog-options' => Json::encode([
//      'width' => 700,
//      'height' => 400,
//    ]),]]
//    );

    if(in_array('service_engineer', $user->getRoles(TRUE))  || in_array('site_manager', $user->getRoles(TRUE))) {
      $links[] = Link::fromTextAndUrl(t('Edit'), Url::fromUri("internal:/admin/structure/webform/manage/contact/submission/$system_number/edit", $edit_options))->toRenderable();
    }

    return [
      '#links' => $links,
      '#theme' => 'action',
    ];
  }

  //Do not allow the block to be cached
  public function getCacheMaxAge() {
    return 0;
  }

  public static function flowPermission($account, $process) {
    /*
     * passing in the system number from url alias will not work because there are multiple web forms on this page
     * we want to check the form factor of the net new inventory one, which happens to be the SID
     */
    $current_path = \Drupal::service('path.current')->getPath();
    preg_match("/[^\/]+$/", $current_path, $matches);
    $sid = $matches[0];

    if($web_form = WebformSubmission::load($sid)){
      $form_factor = $web_form->getElementData('form_factor');
      $workflow_status = $web_form->getElementData('workflow_status');
    }

    //set permissions based on workflow condition
    switch($process){
      case "decommission":
        $user_permission = [
          "regional_manager" => "6",
          "local_pm" => "5"
        ];
        break;
      case "transfer":
        $user_permission = [
          "administrator" => ["1", "2", "3", "4", "5", "6"],
          "local_pm" => ["1", "2", "3", "4", "5", "6"],
          "capital_pm" => ["1", "2", "3", "4", "5", "6"]
        ];
        break;
      default:
        break;
    }

    $access = false;
    //array of user's role, loop through them
    foreach($account->getRoles() as $role){
      /** @var array $user_permission */
      //an array of $user_permission: depends on the workflow
      foreach ($user_permission as $key => $permission) {
        //if role has more than 1 accessibility for form factor, then loop through all the choices. See line 85 example.
        if(is_array($permission)){
          foreach($permission as $formFactor){
            if($role == $key and $form_factor == $formFactor){
              $access = true;
            }
          }
        }
        //if permission does not need more than 1 form factor, just
        else {
          if ($role == $key and $form_factor == $permission) {
            $access = true;
          }
        }
      }
    }

    if(($workflow_status == 'Pending Relocation' && $process == 'transfer') || ($workflow_status == 'Pending Decommission' && $process == 'decommission') ){
      $access = false;
    }

    return $access === false ? false : true;
  }
}
