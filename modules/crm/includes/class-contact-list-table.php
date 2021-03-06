<?php
namespace WeDevs\ERP\CRM;

/**
 * Customer List table class
 *
 * @package weDevs|wperp
 */
class Contact_List_Table extends \WP_List_Table {

    private $counts = array();
    private $page_status = '';
    private $contact_type;
    private $page_type;

    function __construct( $type = null ) {
        global $status, $page;

        parent::__construct( array(
            'singular' => 'customer',
            'plural'   => 'customers',
            'ajax'     => false
        ) );

        if ( $type ) {
            $this->contact_type = $type;
        }

        if ( $this->contact_type == 'contact' ) {
            $this->page_type = 'erp-sales-customers';
        }

        if ( $this->contact_type == 'company' ) {
            $this->page_type = 'erp-sales-companies';
        }
    }

    /**
     * Message to show if no contacts found
     *
     * @since 1.0
     *
     * @return void
     */
    function no_items() {
        echo sprintf( __( 'No %s found.', 'wp-erp' ), $this->contact_type );
    }

    /**
     * Render extra filtering option in
     * top of the table
     *
     * @since 1.0
     *
     * @param  string $which
     *
     * @return void
     */
    function extra_tablenav( $which ) {
        if ( $which != 'top' ) {
            return;
        }

        $save_searches        = erp_get_save_search_item();
        $selected_save_search = ( isset( $_GET['erp_save_search'] ) ) ? $_GET['erp_save_search'] : '';
        ?>

        <div class="alignleft actions">

            <label class="screen-reader-text" for="filter_by_save_searches"><?php _e( 'Filter By Saved Searches', 'wp-erp' ) ?></label>
            <select style="width:250px;" name="filter_by_save_searches" class="selecttwo select2" id="erp_customer_filter_by_save_searches" data-placeholder="<?php _e( 'Select from saved searches', 'wp-erp' ); ?>">
                <?php foreach ( $save_searches as $key => $searches ) : ?>
                    <option value=""></option>
                    <optgroup label="<?php echo $searches['name']; ?>" id="<?php echo strtolower( str_replace(' ', '-', $searches['name'] ) ); ?>">

                        <?php foreach ( $searches['options'] as $option_key => $option_value ) : ?>
                            <option value="<?php echo $option_value['id']; ?>" <?php selected( $selected_save_search, $option_value['id']); ?>><?php echo $option_value['text']; ?></option>
                        <?php endforeach ?>

                    </optgroup>

                <?php endforeach ?>
            </select>

            <?php
            submit_button( __( 'Filter', 'wp-erp' ), 'secondary', 'filter_advance_search_contact', false, [ 'id' => 'erp-advance-filter-contact-btn'] );

            if ( $selected_save_search ) {
                $base_link = add_query_arg( [ 'page' => $this->page_type ], admin_url( 'admin.php' ) );
                echo '<a href="' . $base_link . '" class="button erp-reset-save-search-field" id="erp-reset-save-search-field">' . __( 'Reset', 'wp-erp' ) . '</a>';
                echo '<a href="#" class="button erp-show-save-search-field" id="erp-show-save-search-field">' . __( 'Show Fields', 'wp-erp' ) . '</a>';
            }

        echo '</div>';
    }


    /**
     * Default column values if no callback found
     *
     * @since 1.0
     *
     * @param  object  $item
     * @param  string  $column_name
     *
     * @return string
     */
    function column_default( $customer, $column_name ) {

        $life_stages = erp_crm_get_life_statges_dropdown_raw();
        $life_stage  = erp_people_get_meta( $customer->id, 'life_stage', true );

        switch ( $column_name ) {
            case 'email':
                return $customer->email;

            case 'phone_number':
                return $customer->phone;

            case 'life_stages':
                return isset( $life_stages[$life_stage] ) ? $life_stages[$life_stage] : '-';

            case 'created':
                return erp_format_date( $customer->created );

            default:
                return isset( $customer->$column_name ) ? $customer->$column_name : '';
        }
    }

    /**
     * Render current trggier bulk action
     *
     * @since 1.0
     *
     * @return string [type of filter]
     */
    public function current_action() {

        if ( isset( $_REQUEST['filter_advance_search_contact'] ) ) {
            return 'filter_by_save_searches';
        }

        if ( isset( $_REQUEST['customer_search'] ) ) {
            return 'customer_search';
        }

        return parent::current_action();
    }

    /**
     * Get sortable columns
     *
     * @since 1.0
     *
     * @return array
     */
    function get_sortable_columns() {
        $sortable_columns = array(
            'created' => array( 'created', false ),
        );

        return $sortable_columns;
    }

    /**
     * Get the column names
     *
     * @since 1.0
     *
     * @return array
     */
    function get_columns() {
        $columns = array(
            'cb'           => '<input type="checkbox" />',
            'name'         => sprintf( '%s %s', ucfirst( $this->contact_type ), __( 'Name', 'wp-erp' ) ),
            'email'        => __( 'Email', 'wp-erp' ),
            'phone_number' => __( 'Phone', 'wp-erp' ),
            'life_stages'  => __( 'Life Stage', 'wp-erp' ),
            'created'      => __( 'Created at', 'wp-erp' ),
        );

        return apply_filters( 'erp_hr_customer_table_cols', $columns );
    }

    /**
     * Render the customer name column
     *
     * @since 1.0
     *
     * @param  object  $item
     *
     * @return string
     */
    function column_name( $customer ) {
        $customer          = new \WeDevs\ERP\CRM\Contact( intval( $customer->id ), $this->contact_type );
        $actions           = array();
        $delete_url        = '';
        $view_url          = $customer->get_details_url();
        $data_hard         = ( isset( $_REQUEST['status'] ) && $_REQUEST['status'] == 'trash' ) ? 1 : 0;
        $delete_text       = ( isset( $_REQUEST['status'] ) && $_REQUEST['status'] == 'trash' ) ? __( 'Permanent Delete', 'wp-erp' ) : __( 'Delete', 'wp-erp' );
        $customer_name     = $customer->first_name .' '. $customer->last_name;
        $edit_title        = ( $customer->type == 'company' ) ? __( 'Edit this Company', 'wp-erp' ) : __( 'Edit this customer', 'wp-erp' );
        $view_title        = ( $customer->type == 'company' ) ? __( 'View this Company', 'wp-erp' ) : __( 'View this customer', 'wp-erp' );

        $actions['edit']   = sprintf( '<a href="%s" data-id="%d" title="%s">%s</a>', $delete_url, $customer->id, $edit_title, __( 'Edit', 'wp-erp' ) );
        $actions['view']   = sprintf( '<a href="%s" title="%s">%s</a>', $view_url, $view_title, __( 'View', 'wp-erp' ) );
        $actions['delete'] = sprintf( '<a href="%s" class="submitdelete" data-id="%d" data-hard=%d title="%s">%s</a>', $delete_url, $customer->id, $data_hard, __( 'Delete this item', 'wp-erp' ), $delete_text );

        if ( isset( $_REQUEST['status'] ) && $_REQUEST['status'] == 'trash' ) {
            $actions['restore'] = sprintf( '<a href="%s" class="restoreCustomer" data-id="%d" title="%s">%s</a>', $delete_url, $customer->id, __( 'Restore this item', 'wp-erp' ), __( 'Restore', 'wp-erp' ) );
        }

        return sprintf( '%4$s <a href="%3$s"><strong>%1$s</strong></a> %2$s', $customer->get_full_name(), $this->row_actions( $actions ), $customer->get_details_url(), $customer->get_avatar() );
    }

    /**
     * Set the bulk actions
     *
     * @since 1.0
     *
     * @return array
     */
    function get_bulk_actions() {
        $actions = array(
            'delete'  => __( 'Move to Trash', 'wp-erp' ),
            'assing_group' => __( 'Add to Contact group', 'wp-erp' )
        );

        if ( isset( $_REQUEST['status'] ) && $_REQUEST['status'] == 'trash' ) {
            unset( $actions['delete'] );

            $actions['permanent_delete'] = __( 'Permanent Delete', 'wp-erp' );
            $actions['restore'] = __( 'Restore', 'wp-erp' );
        }

        return $actions;
    }

    /**
     * Render the checkbox column
     *
     * @since 1.0
     *
     * @param  object  $item
     *
     * @return string
     */
    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" class="erp-crm-customer-id-checkbox" name="customer_id[]" value="%s" />', $item->id
        );
    }

    /**
     * Set the filter listing views
     *
     * @since 1.0
     *
     * @return array
     */
    public function get_views() {
        $status_links = array();
        $base_link    = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'customer_search', 'filter_by_save_searches' ), wp_unslash( $_SERVER['REQUEST_URI'] ) );

        foreach ( $this->counts as $key => $value ) {
            $class = ( $key == $this->page_status ) ? 'current' : 'status-' . $key;
            $status_links[ $key ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>', add_query_arg( array( 'status' => $key ), $base_link ), $class, $value['label'], $value['count'] );
        }

        $status_links[ 'trash' ] = sprintf( '<a href="%s" class="status-trash">%s <span class="count">(%s)</span></a>', add_query_arg( array( 'status' => 'trash' ), $base_link ), __( 'Trash', 'wp-erp' ), erp_crm_count_trashed_customers() );

        return $status_links;
    }

    /**
     * Search form for lsit table
     *
     * @since 1.0
     *
     * @param  string $text
     * @param  string $input_id
     *
     * @return void
     */
    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && !$this->has_items() ) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
        }

        if ( ! empty( $_REQUEST['order'] ) ) {
            echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
        }

        if ( ! empty( $_REQUEST['status'] ) ) {
            echo '<input type="hidden" name="status" value="' . esc_attr( $_REQUEST['status'] ) . '" />';
        }

        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button( $text, 'button', 'customer_search', false, array( 'id' => 'search-submit' ) ); ?>
            <a href="#" class="button button-primary erp-advance-search-button" id="erp-advance-search-button"><span class="dashicons dashicons-admin-generic"></span>Advance Search</a>
        </p>
        <?php
    }

    /**
     * Prepare the class items
     *
     * @since 1.0
     *
     * @return void
     */
    function prepare_items() {
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $per_page              = 20;
        $current_page          = $this->get_pagenum();
        $offset                = ( $current_page -1 ) * $per_page;
        $this->page_status     = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';

        // only ncessary because we have sample data
        $args = [
            'type'   => $this->contact_type,
            'offset' => $offset,
            'number' => $per_page,
        ];

        // Filter for serach
        if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
            $args['s'] = $_REQUEST['s'];
        }

        // Filter for order & order by
        if ( isset( $_REQUEST['orderby'] ) && isset( $_REQUEST['order'] ) ) {
            $args['orderby']  = $_REQUEST['orderby'];
            $args['order']    = $_REQUEST['order'] ;
        } else {
            $args['orderby']  = 'created';
            $args['order']    = 'desc';
        }

        // Filter for cusotmer life stage
        if ( isset( $_REQUEST['status'] ) && ! empty( $_REQUEST['status'] ) ) {
            if ( $_REQUEST['status'] != 'all' ) {
                if ( $_REQUEST['status'] == 'trash' ) {
                    $args['trashed'] = true;
                } else {
                    $args['meta_query'] = [
                        'meta_key' => 'life_stage',
                        'meta_value' => $_REQUEST['status']
                    ];
                }
            }
        }

        // Total counting for customer type filter
        $this->counts = erp_crm_customer_get_status_count( $this->contact_type );

        // Prepare all item after all filtering
        $this->items  = erp_get_peoples( $args );

        // Render total customer according to above filter
        $args['count'] = true;
        $total_items = erp_get_peoples( $args );

        // Set pagination according to filter
        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page
        ] );
    }

}
