<?php 
/*
  Plugin name: Pianodragon customisation
  Author: Vivek V.
*/

class pianodragonCustomisation{

  ## Form & Fields
  public $form = [
    'id'     => 3,
    'fields' => [
      'email'    => 3,
      'plan'     => 22,
      'user_id'  => 21
    ]
  ];

  public function __construct(){
    add_filter( 'gform_pre_render_'.$this->form['id'], [ $this, 'populate_user_id' ] );
    add_action( 'gform_activate_user', [ $this, 'after_user_activate' ], 99, 3 );
    add_filter( 'gform_save_field_value_'.$this->form['id'].'_'.$this->form['fields']['plan'], [ $this, 'update_course_type' ], 10, 5 );
    add_filter( 'gform_entry_id_pre_save_lead_'.$this->form['id'], [ $this, 'updateEntity' ], 20, 2 );
    add_action( 'gform_user_registered', [ $this, 'update_user_id' ], 10, 4 );
  }

  ## Get user entry
  public function get_user_entry( $user_id ){
    ## Getting the entries
    $search_criteria = array(
      'status'        => 'active',
      'field_filters' => array(
          array(
              'key'   => $this->form['fields']['user_id'],
              'value' => $user_id
          )
      )
    );
    $entries = GFAPI::get_entries( $form['id'], $search_criteria );
    if( empty($entries) ){
      return [];
    }
    return $entries[0];
  }

  ## Prepopulate logged in user ID
  public function populate_user_id( $form ) {
    foreach ( $form['fields'] as &$field ) {
        if ( $field->id == $this->form['fields']['user_id'] ) {
            $field->defaultValue = get_current_user_id();
        }
        if ( $field->id == $this->form['fields']['email'] && is_user_logged_in() ) {
          $entry = $this->get_user_entry( get_current_user_id() );
          if( !empty($entry) ){
            $field->defaultValue = rgar( $entry, $this->form['fields']['email'] );
            $field->cssClass = $field->cssClass." hidden-box cs-hide";
          }
      }
    }
    return $form;
  }

  ## Process update course
  public function maybe_process_course( $entry=[] ){

    ## Validating entry
    if( empty( $entry ) ){
      return false;
    }
    
    ## Get form fields from custom array
    $fields = $this->form['fields'];

    ## Get user details
    $user = get_user_by_email( rgar( $entry, $fields['email'] ) );
    $user_id = $user->ID; 

    if( !empty( rgar( $entry, '22.1' ) ) ){
      update_user_meta( $user_id, 'learndash_group_users_6014', 6014 );
      return false;
    }

    $courses = [
      'course_70_access_from',
      'course_4950_access_from',
      'learndash_group_users_6014',
      'course_258_access_from',
    ];
    
    ## Rhythm Training
    if( !empty( rgar( $entry, '22.2' ) ) ){
      unset($courses[1]);
      update_user_meta( $user_id, 'course_4950_access_from', time() );
    }

    ## Bach Made Easy
    if( !empty( rgar( $entry, '22.3' ) ) ){
      unset($courses[0]);
      update_user_meta( $user_id, 'course_70_access_from', time() );
    }

    ## Delete restricted courses
    foreach( $courses as $course ){
      delete_user_meta( $user_id, $course );
    }
    return true;
  }

  ## Process this function after new user activated
  public function after_user_activate( $user_id, $user_data, $signup_meta ) {
    ## Get entry
    $entry = GFAPI::get_entry( $signup_meta['entry_id'] );
    if( is_wp_error($entry) ){
      return false;
    }
    $this->maybe_process_course($entry);
  }

  ## Update LearnDash course after Gravity form entry updated
  public function update_course_type( $value, $entry, $field, $form, $input_id ){
    $entry[$input_id] = $value;
    $this->maybe_process_course($entry);
    return $value;
  }

  ## Update entity meta
  public function updateEntity( $entryId, $form ) {

    if( is_admin() ){
      return $entryId;
    }

    $user_id = get_current_user_id();
    if( $user_id == 0 ){
      return $entryId;
    }

    ## Existing entry
    $lead =  $this->get_user_entry( get_current_user_id() );
    if( empty($lead) ){
      return $entryId;
    }

    $_POST['input_3']    = rgar( $lead, '3' );
    $_POST['input_22_1'] = !empty($_POST['input_22_1']) ? $_POST['input_22_1'] : rgar( $lead, '22.1' );
    $_POST['input_22_2'] = !empty($_POST['input_22_2']) ? $_POST['input_22_2'] : rgar( $lead, '22.2' );
    $_POST['input_22_3'] = !empty($_POST['input_22_3']) ? $_POST['input_22_3'] : rgar( $lead, '22.3' );

    if( !empty($_POST['input_22_1']) ){
      GFAPI::update_entry_field( $lead['id'], '22.1', $_POST['input_22_1'] );
    }

    if( !empty($_POST['input_22_2']) ){
      GFAPI::update_entry_field( $lead['id'], '22.2', $_POST['input_22_2'] );
    }

    if( !empty($_POST['input_22_3']) ){
      GFAPI::update_entry_field( $lead['id'], '22.3', $_POST['input_22_3'] );
    }

    return $lead['id'];
  }

  ## After user registration
  public function update_user_id( $user_id, $feed, $entry, $user_pass ) {
    if( $entry['form_id'] != 3 ){
      return;
    }
    GFAPI::update_entry_field( $entry['id'], $this->form['fields']['user_id'], $user_id );
    $this->maybe_process_course($entry);
  }

}

add_action( 'plugins_loaded', function(){
  new pianodragonCustomisation();
});
