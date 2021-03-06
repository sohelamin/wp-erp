<?php

/**
 * Delete an employee if removed from WordPress usre table
 *
 * @param  int  the user id
 *
 * @return void
 */
function erp_hr_employee_on_delete( $user_id, $hard = 0 ) {
    global $wpdb;

    $user = get_user_by( 'id', $user_id );

    if ( ! $user ) {
        return;
    }

    $role = reset( $user->roles );

    if ( 'employee' == $role ) {
        \WeDevs\ERP\HRM\Models\Employee::where( 'user_id', $user_id )->withTrashed()->forceDelete();
    }
}

/**
 * Create a new employee
 *
 * @param  array  arguments
 *
 * @return int  employee id
 */
function erp_hr_employee_create( $args = array() ) {

    global $wpdb;

    $defaults = array(
        'user_email'      => '',
        'work'            => array(
            'designation'   => 0,
            'department'    => 0,
            'location'      => '',
            'hiring_source' => '',
            'hiring_date'   => '',
            'date_of_birth' => '',
            'reporting_to'  => 0,
            'pay_rate'      => '',
            'pay_type'      => '',
            'type'          => '',
            'status'        => '',
        ),
        'personal'        => array(
            'photo_id'        => 0,
            'user_id'         => 0,
            'first_name'      => '',
            'middle_name'     => '',
            'last_name'       => '',
            'other_email'     => '',
            'phone'           => '',
            'work_phone'      => '',
            'mobile'          => '',
            'address'         => '',
            'gender'          => '',
            'marital_status'  => '',
            'nationality'     => '',
            'driving_license' => '',
            'hobbies'         => '',
            'user_url'        => '',
            'description'     => '',
        )
    );

    $posted = array_map( 'strip_tags_deep', $args );
    $posted = array_map( 'trim_deep', $posted );
    $data   = wp_parse_args( $posted, $defaults );

    // some basic validation
    if ( empty( $data['personal']['first_name'] ) ) {
        return new WP_Error( 'empty-first-name', __( 'Please provide the first name.', 'wp-erp' ) );
    }

    if ( empty( $data['personal']['last_name'] ) ) {
        return new WP_Error( 'empty-last-name', __( 'Please provide the last name.', 'wp-erp' ) );
    }

    if ( ! is_email( $data['user_email'] ) ) {
        return new WP_Error( 'invalid-email', __( 'Please provide a valid email address.', 'wp-erp' ) );
    }

    // attempt to create the user
    $userdata = array(
        'user_login'   => $data['user_email'],
        'user_email'   => $data['user_email'],
        'first_name'   => $data['personal']['first_name'],
        'last_name'    => $data['personal']['last_name'],
        'display_name' => $data['personal']['first_name'] . ' ' . $data['personal']['middle_name'] . ' ' . $data['personal']['last_name'],
    );

    // if user id exists, do an update
    $user_id = isset( $posted['user_id'] ) ? intval( $posted['user_id'] ) : 0;
    $update  = false;

    if ( $user_id ) {
        $update = true;
        $userdata['ID'] = $user_id;

    } else {
        // when creating a new user, assign role and passwords
        $userdata['user_pass'] = wp_generate_password( 12 );
        $userdata['role'] = 'employee';
    }

    $userdata = apply_filters( 'erp_hr_employee_args', $userdata );
    $user_id  = wp_insert_user( $userdata );

    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    // if reached here, seems like we have success creating the user
    $employee = new \WeDevs\ERP\HRM\Employee( $user_id );

    // inserting the user for the first time
    $hiring_date = ! empty( $data['work']['hiring_date'] ) ? $data['work']['hiring_date'] : current_time( 'mysql' );
    if ( ! $update ) {

        $work        = $data['work'];

        if ( ! empty( $work['type'] ) ) {
            $employee->update_employment_status( $work['type'], $hiring_date );
        }

        // update compensation
        if ( ! empty( $work['pay_rate'] ) ) {
            $pay_type = ( ! empty( $work['pay_type'] ) ) ? $work['pay_type'] : 'monthly';
            $employee->update_compensation( $work['pay_rate'], $pay_type, '', $hiring_date );
        }

        // update job info
        $employee->update_job_info( $work['department'], $work['designation'], $work['reporting_to'], $work['location'], $hiring_date );
    }


    $employee_table_data = array(
        'hiring_source' => $data['work']['hiring_source'],
        'hiring_date'   => $hiring_date,
        'date_of_birth' => $data['work']['date_of_birth'],
        'employee_id'   => $data['personal']['employee_id']
    );

    if ( ! $update ) {
        $employee_table_data['status'] = $data['work']['status'];
    }

    // update the erp table
    $wpdb->update( $wpdb->prefix . 'erp_hr_employees', $employee_table_data, array( 'user_id' => $user_id ) );

    foreach ( $data['personal'] as $key => $value ) {

        if ( 'employee_id' == $key ) {
            continue;
        }

        update_user_meta( $user_id, $key, $value );
    }

    if ( $update ) {
        do_action( 'erp_hr_employee_update', $user_id, $data );
    } else {
        do_action( 'erp_hr_employee_new', $user_id, $data );
    }

    return $user_id;
}

/**
 * Get all employees from a company
 *
 * @param  int   $company_id  company id
 * @param bool $no_object     if set true, Employee object will be
 *                            returned as array. $wpdb rows otherwise
 *
 * @return array  the employees
 */
function erp_hr_get_employees( $args = array() ) {
    global $wpdb;

    $defaults = array(
        'number'     => 20,
        'offset'     => 0,
        'orderby'    => 'hiring_date',
        'order'      => 'DESC',
        'no_object'  => false
    );

    $args  = wp_parse_args( $args, $defaults );
    $where = array();

    $employee = new \WeDevs\ERP\HRM\Models\Employee();
    $employee_result = $employee->leftjoin( $wpdb->users, 'user_id', '=', $wpdb->users . '.ID' )->select( array( 'user_id', 'display_name' ) );

    if ( isset( $args['designation'] ) && $args['designation'] != '-1' ) {
        $employee_result = $employee_result->where( 'designation', $args['designation'] );
    }

    if ( isset( $args['department'] ) && $args['department'] != '-1' ) {
        $employee_result = $employee_result->where( 'department', $args['department'] );
    }

    if ( isset( $args['location'] ) && $args['location'] != '-1' ) {
        $employee_result = $employee_result->where( 'location', $args['location'] );
    }

    if ( isset( $args['type'] ) && $args['type'] != '-1' ) {
        $employee_result = $employee_result->where( 'type', $args['type'] );
    }

    if ( isset( $args['status'] ) && ! empty( $args['status'] ) ) {
        if ( $args['status'] == 'trash' ) {
            $employee_result = $employee_result->onlyTrashed();
        } else {
            $employee_result = $employee_result->where( 'status', $args['status'] );
        }
    }

    if ( isset( $args['s'] ) && ! empty( $args['s'] ) ) {
        $arg_s = $args['s'];
        $employee_result = $employee_result->where( 'display_name', 'LIKE', "%$arg_s%" );
    }

    $cache_key = 'erp-get-employees-' . md5( serialize( $args ) );
    $results   = wp_cache_get( $cache_key, 'wp-erp' );
    $users     = array();

    if ( false === $results ) {
        $results = $employee_result->skip( $args['offset'] )
                    ->take( $args['number'] )
                    ->orderBy( $args['orderby'], $args['order'] )
                    ->get()
                    ->toArray();

        $results = erp_array_to_object( $results );
        wp_cache_set( $cache_key, $results, 'wp-erp', HOUR_IN_SECONDS );
    }

    if ( $results ) {
        foreach ($results as $key => $row) {

            if ( true === $args['no_object'] ) {
                $users[] = $row;
            } else {
                $users[] = new \WeDevs\ERP\HRM\Employee( intval( $row->user_id ) );
            }
        }
    }

    return $users;
}


/**
 * Get all employees from a company
 *
 * @param  int   $company_id  company id
 * @param bool $no_object     if set true, Employee object will be
 *                            returned as array. $wpdb rows otherwise
 *
 * @return array  the employees
 */
function erp_hr_count_employees() {

    $where = array();

    $employee = new \WeDevs\ERP\HRM\Models\Employee();

    if ( isset( $args['designation'] ) && ! empty( $args['designation'] ) ) {
        $designation = array( 'designation' => $args['designation'] );
        $where = array_merge( $designation, $where );
    }

    if ( isset( $args['department'] ) && ! empty( $args['department'] ) ) {
        $department = array( 'department' => $args['department'] );
        $where = array_merge( $where, $department );
    }

    if ( isset( $args['location'] ) && ! empty( $args['location'] ) ) {
        $location = array( 'location' => $args['location'] );
        $where = array_merge( $where, $location );
    }

    if ( isset( $args['status'] ) && ! empty( $args['status'] ) ) {
        $status = array( 'status' => $args['status'] );
        $where = array_merge( $where, $status );
    }

    $counts = $employee->where( $where )->count();

    return $counts;
}


/**
 * Get Employee status count
 *
 * @since 0.1
 *
 * @return array
 */
function erp_hr_employee_get_status_count() {
    global $wpdb;

    $statuses = array( 'all' => __( 'All', 'wp-erp' ) ) + erp_hr_get_employee_statuses();
    $counts   = array();

    foreach ( $statuses as $status => $label ) {
        $counts[ $status ] = array( 'count' => 0, 'label' => $label );
    }

    $cache_key = 'erp-hr-employee-status-counts';
    $results = wp_cache_get( $cache_key, 'wp-erp' );

    if ( false === $results ) {

        $employee = new \WeDevs\ERP\HRM\Models\Employee();
        $db = new \WeDevs\ORM\Eloquent\Database();

        $results = $employee->select( array( 'status', $db->raw('COUNT(id) as num') ) )
                            ->where( 'status', '!=', '0' )
                            ->groupBy('status')
                            ->get()->toArray();

        wp_cache_set( $cache_key, $results, 'wp-erp' );
    }

    foreach ( $results as $row ) {
        if ( array_key_exists( $row['status'], $counts ) ) {
            $counts[ $row['status'] ]['count'] = (int) $row['num'];
        }

        $counts['all']['count'] += (int) $row['num'];
    }

    return $counts;
}

/**
 * Count trash employee
 *
 * @since 0.1
 *
 * @return int [no of trash employee]
 */
function erp_hr_count_trashed_employees() {
    $employee = new \WeDevs\ERP\HRM\Models\Employee();

    return $employee->onlyTrashed()->count();
}

/**
 * Employee Restore from trash
 *
 * @since 0.1
 *
 * @param  array|int $employee_ids
 *
 * @return void
 */
function erp_employee_restore( $employee_ids ) {
    if ( empty( $employee_ids ) ) {
        return;
    }

    if ( is_array( $employee_ids ) ) {
        foreach ( $employee_ids as $key => $user_id ) {
            \WeDevs\ERP\HRM\Models\Employee::withTrashed()->where( 'user_id', $user_id )->restore();
        }
    }

    if ( is_int( $employee_ids ) ) {
        \WeDevs\ERP\HRM\Models\Employee::withTrashed()->where( 'user_id', $employee_ids )->restore();
    }
}

/**
 * Employee Delete
 *
 * @param  array|int $employee_ids
 *
 * @return void
 */
function erp_employee_delete( $employee_ids, $hard = false ) {

    if ( empty( $employee_ids ) ) {
        return;
    }

    $employees = [];

    if ( is_array( $employee_ids ) ) {
        foreach ( $employee_ids as $key => $user_id ) {
            $employees[] = $user_id;
        }
    } else if ( is_int( $employee_ids ) ) {
        $employees[] = $employee_ids;
    }

    // still do we have any ids to delete?
    if ( ! $employees ) {
        return;
    }

    // seems like we got some
    foreach ($employees as $employee_id) {
        if ( $hard ) {
            \WeDevs\ERP\HRM\Models\Employee::where( 'user_id', $employee_id )->withTrashed()->forceDelete();
            wp_delete_user( $employee_id );

            // find leave entitlements and leave requests and delete them as well
            \WeDevs\ERP\HRM\Models\Leave_request::where( 'user_id', '=', $employee_id )->delete();
            \WeDevs\ERP\HRM\Models\Leave_Entitlement::where( 'user_id', '=', $employee_id )->delete();

        } else {
            \WeDevs\ERP\HRM\Models\Employee::where( 'user_id', $employee_id )->delete();
        }

        do_action( 'erp_hr_delete_employee', $employee_id, $hard );
    }

}

/**
 * Get Todays Birthday
 *
 * @since 0.1
 *
 * @return object [collection of user_id]
 */
function erp_hr_get_todays_birthday() {

    $db = new \WeDevs\ORM\Eloquent\Database();

    return erp_array_to_object( \WeDevs\ERP\HRM\Models\Employee::select('user_id')
            ->where( $db->raw("DATE_FORMAT( `date_of_birth`, '%m %d' )" ), \Carbon\Carbon::today()->format('m d') )
            ->get()
            ->toArray() );
}

/**
 * Get next seven days birthday
 *
 * @since 0.1
 *
 * @return object [user_id, date_of_birth]
 */
function erp_hr_get_next_seven_days_birthday() {

    $db = new \WeDevs\ORM\Eloquent\Database();

    return erp_array_to_object( \WeDevs\ERP\HRM\Models\Employee::select( array( 'user_id', 'date_of_birth' ) )
            ->where( $db->raw("DATE_FORMAT( `date_of_birth`, '%m %d' )" ), '>', \Carbon\Carbon::today()->format('m d') )
            ->where( $db->raw("DATE_FORMAT( `date_of_birth`, '%m %d' )" ), '<=', \Carbon\Carbon::tomorrow()->addWeek()->format('m d') )
            ->get()
            ->toArray() );
}

/**
 * Get the raw employees dropdown
 *
 * @param  int  company id
 *
 * @return array  the key-value paired employees
 */
function erp_hr_get_employees_dropdown_raw( $exclude = null ) {
    $employees = erp_hr_get_employees( array( 'no_object' => true ) );
    $dropdown  = array( 0 => __( '- Select Employee -', 'wp-erp' ) );

    if ( $employees ) {
        foreach ($employees as $key => $employee) {
            if ( $exclude && $employee->user_id == $exclude ) {
                continue;
            }

            $dropdown[$employee->user_id] = $employee->display_name;
        }
    }

    return $dropdown;
}

/**
 * Get company employees dropdown
 *
 * @param  int  company id
 * @param  string  selected department
 *
 * @return string  the dropdown
 */
function erp_hr_get_employees_dropdown( $selected = '' ) {
    $employees = erp_hr_get_employees_dropdown_raw();
    $dropdown  = '';

    if ( $employees ) {
        foreach ($employees as $key => $title) {
            $dropdown .= sprintf( "<option value='%s'%s>%s</option>\n", $key, selected( $selected, $key, false ), $title );
        }
    }

    return $dropdown;
}

/**
 * Get the registered employee statuses
 *
 * @return array the employee statuses
 */
function erp_hr_get_employee_statuses() {
    $statuses = array(
        'active'     => __( 'Active', 'wp-erp' ),
        'terminated' => __( 'Terminated', 'wp-erp' ),
        'deceased'   => __( 'Deceased', 'wp-erp' ),
        'resigned'   => __( 'Resigned', 'wp-erp' )
    );

    return apply_filters( 'erp_hr_employee_statuses', $statuses );
}

/**
 * Get the registered employee statuses
 *
 * @return array the employee statuses
 */
function erp_hr_get_employee_statuses_icons( $selected = NULL ) {
    $statuses = apply_filters( 'erp_hr_employee_statuses_icons', array(
        'active'     => sprintf( '<span class="erp-tips dashicons dashicons-yes" title="%s"></span>', __( 'Active', 'wp-erp' ) ),
        'terminated' => sprintf( '<span class="erp-tips dashicons dashicons-dismiss" title="%s"></span>', __( 'Terminated', 'wp-erp' ) ),
        'deceased'   => sprintf( '<span class="erp-tips dashicons dashicons-marker" title="%s"></span>', __( 'Deceased', 'wp-erp' ) ),
        'resigned'   => sprintf( '<span class="erp-tips dashicons dashicons-warning" title="%s"></span>', __( 'Resigned', 'wp-erp' ) )
    ) );

    if ( $selected && array_key_exists( $selected, $statuses ) ) {
        return $statuses[$selected];
    }

    return false;
}


/**
 * Get the registered employee statuses
 *
 * @return array the employee statuses
 */
function erp_hr_get_employee_types() {
    $types = array(
        'permanent' => __( 'Full Time', 'wp-erp' ),
        'parttime'  => __( 'Part Time', 'wp-erp' ),
        'contract'  => __( 'On Contract', 'wp-erp' ),
        'temporary' => __( 'Temporary', 'wp-erp' ),
        'trainee'   => __( 'Trainee', 'wp-erp' )
    );

    return apply_filters( 'erp_hr_employee_types', $types );
}

/**
 * Get the registered employee hire sources
 *
 * @return array the employee hire sources
 */
function erp_hr_get_employee_sources() {
    $sources = array(
        'direct'        => __( 'Direct', 'wp-erp' ),
        'referral'      => __( 'Referral', 'wp-erp' ),
        'web'           => __( 'Web', 'wp-erp' ),
        'newspaper'     => __( 'Newspaper', 'wp-erp' ),
        'advertisement' => __( 'Advertisement', 'wp-erp' ),
        'social'        => __( 'Social Network', 'wp-erp' ),
        'other'         => __( 'Other', 'wp-erp' ),
    );

    return apply_filters( 'erp_hr_employee_sources', $sources );
}

/**
 * Get marital statuses
 *
 * @return array all the statuses
 */
function erp_hr_get_marital_statuses( $select_text = null ) {

    if ( $select_text ) {
        $statuses = array(
            '-1'      => $select_text,
            'single'  => __( 'Single', 'wp-erp' ),
            'married' => __( 'Married', 'wp-erp' ),
            'widowed' => __( 'Widowed', 'wp-erp' )
        );
    } else {
        $statuses = array(
            'single'  => __( 'Single', 'wp-erp' ),
            'married' => __( 'Married', 'wp-erp' ),
            'widowed' => __( 'Widowed', 'wp-erp' )
        );
    }

    return apply_filters( 'erp_hr_marital_statuses',  $statuses );
}

/**
 * Get Terminate Type
 *
 * @return array all the type
 */
function erp_hr_get_terminate_type( $selected = NULL ) {
    $type = apply_filters( 'erp_hr_terminate_type', array(
        'voluntary'   => __( 'Voluntary', 'wp-erp' ),
        'involuntary' => __( 'Involuntary', 'wp-erp' ),
        'death'       => __( 'Death', 'wp-erp' )
    ) );

    if ( $selected ) {
        return ( isset( $type[$selected] ) ) ? $type[$selected] : '';
    }

    return $type;
}

/**
 * Get Terminate Reason
 *
 * @return array all the reason
 */
function erp_hr_get_terminate_reason( $selected = NULL ) {
    $reason = apply_filters( 'erp_hr_terminate_reason', array(
        'attendance'        => __( 'Attendance', 'wp-erp' ),
        'other_employement' => __( 'Other Employment', 'wp-erp' ),
        'relocation'        => __( 'Relocation', 'wp-erp' )
    ) );

    if ( $selected ) {
        return ( isset( $reason[$selected] ) ) ? $reason[$selected] : '';
    }

    return $reason;
}

/**
 * Get Terminate Reason
 *
 * @return array all the reason
 */
function erp_hr_get_terminate_rehire_options( $selected = NULL ) {
    $reason = apply_filters( 'erp_hr_terminate_rehire_option', array(
        'yes'         => __( 'Yes', 'wp-erp' ),
        'no'          => __( 'No', 'wp-erp' ),
        'upon_review' => __( 'Upon Review', 'wp-erp' )
    ) );

    if ( $selected ) {
        return ( isset( $reason[$selected] ) ) ? $reason[$selected] : '';
    }

    return $reason;
}

/**
 * Get marital statuses
 *
 * @return array all the statuses
 */
function erp_hr_get_genders( $select_text = null ) {

    if ( $select_text ) {
        $genders = array(
            '-1'     => $select_text,
            'male'   => __( 'Male', 'wp-erp' ),
            'female' => __( 'Female', 'wp-erp' ),
            'other'  => __( 'Other', 'wp-erp' )
        );
    } else {
        $genders = array(
            'male'   => __( 'Male', 'wp-erp' ),
            'female' => __( 'Female', 'wp-erp' ),
            'other'  => __( 'Other', 'wp-erp' )
        );
    }

    return apply_filters( 'erp_hr_genders', $genders );
}

/**
 * Get marital statuses
 *
 * @return array all the statuses
 */
function erp_hr_get_pay_type() {
    $genders = array(
        'hourly'   => __( 'Hourly', 'wp-erp' ),
        'daily'    => __( 'Daily', 'wp-erp' ),
        'weekly'   => __( 'Weekly', 'wp-erp' ),
        'monthly'  => __( 'Monthly', 'wp-erp' ),
        'yearly'   => __( 'Yearly', 'wp-erp' ),
        'contract' => __( 'Contract', 'wp-erp' ),
    );

    return apply_filters( 'erp_hr_pay_type', $genders );
}

/**
 * Get marital statuses
 *
 * @return array all the statuses
 */
function erp_hr_get_pay_change_reasons() {
    $genders = array(
        'promotion'   => __( 'Promotion', 'wp-erp' ),
        'performance' => __( 'Performance', 'wp-erp' )
    );

    return apply_filters( 'erp_hr_pay_change_reasons', $genders );
}

/**
 * Add a new item in employee history table
 *
 * @param  array   $args
 *
 * @return void
 */
function erp_hr_employee_add_history( $args = array() ) {
    global $wpdb;

    $defaults = array(
        'user_id'  => 0,
        'module'   => '',
        'category' => '',
        'type'     => '',
        'comment'  => '',
        'data'     => '',
        'date'     => current_time( 'mysql' )
    );

    $data = wp_parse_args( $args, $defaults );
    $format = array(
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s'
    );

    $wpdb->insert( $wpdb->prefix . 'erp_hr_employee_history', $data, $format );
}

/**
 * Remove an item from the history
 *
 * @param  int  $history_id
 *
 * @return bool
 */
function erp_hr_employee_remove_history( $history_id ) {
    global $wpdb;

    return $wpdb->delete( $wpdb->prefix . 'erp_hr_employee_history', array( 'id' => $history_id ) );
}

/**
 * [erp_hr_url_single_employee description]
 *
 * @param  int  employee id
 *
 * @return string  url of the employee details page
 */
function erp_hr_url_single_employee( $employee_id ) {
    $url = admin_url( 'admin.php?page=erp-hr-employee&action=view&id=' . $employee_id );

    return apply_filters( 'erp_hr_url_single_employee', $url, $employee_id );
}

/**
 * Get Employee Announcement List
 *
 * @since 0.1
 *
 * @param  integer $user_id
 *
 * @return array
 */
function erp_hr_employee_dashboard_announcement( $user_id ) {
    global $wpdb;

    return erp_array_to_object( \WeDevs\ERP\HRM\Models\Announcement::join( $wpdb->posts, 'post_id', '=', $wpdb->posts . '.ID' )
            ->where( 'user_id', '=', $user_id )
            ->orderby( $wpdb->posts . '.post_date', 'desc' )
            ->take(8)
            ->get()
            ->toArray() );
}

/**
 * [erp_hr_employee_single_tab_general description]
 *
 * @return void
 */
function erp_hr_employee_single_tab_general( $employee ) {
    include WPERP_HRM_VIEWS . '/employee/tab-general.php';
}

/**
 * [erp_hr_employee_single_tab_job description]
 *
 * @return void
 */
function erp_hr_employee_single_tab_job( $employee ) {
    include WPERP_HRM_VIEWS . '/employee/tab-job.php';
}

/**
 * [erp_hr_employee_single_tab_leave description]
 *
 * @return void
 */
function erp_hr_employee_single_tab_leave( $employee ) {
    include WPERP_HRM_VIEWS . '/employee/tab-leave.php';
}

/**
 * [erp_hr_employee_single_tab_notes description]
 *
 * @return void
 */
function erp_hr_employee_single_tab_notes( $employee ) {
    include WPERP_HRM_VIEWS . '/employee/tab-notes.php';
}

/**
 * [erp_hr_employee_single_tab_performance description]
 *
 * @return void
 */
function erp_hr_employee_single_tab_performance( $employee ) {
    include WPERP_HRM_VIEWS . '/employee/tab-performance.php';
}

/**
 * [erp_hr_employee_single_tab_permission description]
 *
 * @return void
 */
function erp_hr_employee_single_tab_permission( $employee ) {
    include WPERP_HRM_VIEWS . '/employee/tab-permission.php';
}
