<?php

/**
 * Get company work days
 *
 * @param  int  $company_id
 *
 * @return array
 */
function erp_hr_get_work_days() {
    $default = array(
        'mon' => 8,
        'tue' => 8,
        'wed' => 8,
        'thu' => 8,
        'fri' => 8,
        'sat' => 0,
        'sun' => 0
    );

    $option_key = 'erp_settings_erp-hr_workdays';

    return get_option( $option_key, $default );
}

/**
 * Get working day without off day
 *
 * @since  0.1
 *
 * @param  string $start_date
 * @param  string $end_date
 *
 * @return array
 */
function erp_hr_get_work_days_without_off_day( $start_date, $end_date ) {

    $between_dates = erp_extract_dates( $start_date, $end_date );

    if ( is_wp_error( $between_dates ) ) {
        return $between_dates;
    }

    $dates         = array( 'days' => array(), 'total' => 0 );
    $work_days     = erp_hr_get_work_days();
    $holiday_exist = erp_hr_leave_get_holiday_between_date_range( $start_date, $end_date );

    foreach ( $between_dates as $date ) {

        $key       = strtolower( date( 'D', strtotime( $date ) ) );
        $is_holidy = ( $work_days[$key] === 0 ) ? true : false;

        if ( ! $is_holidy ) {
            $is_holidy = in_array( $date, $holiday_exist ) ? true : false;
        }

        if ( ! $is_holidy ) {

            $dates['days'][] = array(
                'date'  => $date,
                'count' => (int) ! $is_holidy
            );

            $dates['total'] += 1;
        }
    }

    return $dates;
}

/**
 * Get working day with off day
 *
 * @since  0.1
 *
 * @param  string $start_date
 * @param  string $end_date
 *
 * @return array
 */
function erp_hr_get_work_days_between_dates( $start_date, $end_date ) {

    $between_dates = erp_extract_dates( $start_date, $end_date );

    if ( is_wp_error( $between_dates ) ) {
        return $between_dates;
    }

    $dates         = array( 'days' => array(), 'total' => 0 );
    $work_days     = erp_hr_get_work_days();
    $holiday_exist = erp_hr_leave_get_holiday_between_date_range( $start_date, $end_date );

    foreach ( $between_dates as $date ) {

        $key       = strtolower( date( 'D', strtotime( $date ) ) );
        $is_holidy = ( $work_days[$key] === 0 ) ? true : false;

        if ( ! $is_holidy ) {
            $is_holidy = in_array( $date, $holiday_exist ) ? true : false;
        }

        $dates['days'][] = array(
            'date'  => $date,
            'count' => (int) ! $is_holidy
        );

        if ( ! $is_holidy ) {
            $dates['total'] += 1;
        }
    }

    return $dates;
}



/**
 * sort parents before children
 *
 * @param array   $objects input objects with attributes 'id' and 'parent'
 * @param array   $result  (optional, reference) internal
 * @param integer $parent  (optional) internal
 * @param integer $depth   (optional) internal
 *
 * @return array           output
 */
function erp_parent_sort( array $objects, array &$result=array(), $parent=0, $depth=0 ) {
    foreach ($objects as $key => $object) {
        if ($object->parent == $parent) {
            $object->depth = $depth;
            array_push($result, $object);
            unset($objects[$key]);
            erp_parent_sort($objects, $result, $object->id, $depth + 1);
        }
    }
    return $result;
}

/**
 * HR related people types
 *
 * @since 1.0
 *
 * @param  array $types
 *
 * @return array
 */
function erp_hr_people_types( $types ) {

    $types['hrm'] = [
        'employee' => [
            'label'    => __( 'Employee', 'wp-erp' ),
            'sections' => [
                'top'      => __( 'Top Area', 'wp-erp' ),
                'basic'    => __( 'Basic Information', 'wp-erp' ),
                'work'     => __( 'Work Information', 'wp-erp' ),
                'personal' => __( 'Personal Information', 'wp-erp' ),
                'bottom'   => __( 'Bottom Area', 'wp-erp' ),
            ]
        ]
    ];

    return apply_filters( 'erp_hr_people_types', $types );
}