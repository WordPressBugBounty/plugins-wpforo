<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;
if( ! current_user_can( 'administrator' ) ) exit;

function wpforo_debug_page_ajax_actions() { ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#wpf-debug-tables-solve-button').on('click', function () {
                $(this).prepend('<i class="fas fa-spinner fa-spin"></i>')
                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'wpforo_update_database',
                        _wpnonce: '<?php echo wp_create_nonce( 'wpforo_update_database' ) ?>'
                    }
                }).done(function () {
                    window.location.reload()
                })
            })
        })
    </script>
    <?php
}

add_action( 'admin_footer', 'wpforo_debug_page_ajax_actions' );
?>
<div class="wpf-tool-box" style="width:98%; margin-left: 1%; border: none;">
    <table style="width:100%;">
        <tbody style="padding:10px;">
        <?php $problems = wpforo_database_check(); ?>
        <?php if( $problems ): ?>
            <tr>
                <td><h3 style="color: #aa0000; margin: 0; border-bottom: none; padding: 15px 0 0 0"><span class="dashicons dashicons-warning" style="vertical-align: sub;"></span>&nbsp;<?php _e( 'Problems Found in Database', 'wpforo' ) ?></h3></td>
            </tr>
            <tr style="background-color: transparent;">
                <td style="padding-bottom: 50px;">
                    <div style="font-size: 14px;">
                        <div style="font-weight: 600; display: flex; flex-direction: row; justify-content: space-between; padding: 5px 10px; border-bottom: 1px solid #cc0000; margin-bottom: 5px; color: #333333; background-color: #f9f9f9;">
                            <div style="width: 30%"><?php _e( 'Table name', 'wpforo' ); ?></div>
                            <div style="text-align: left; flex-grow: 1;"><?php _e( 'Problem description', 'wpforo' ); ?></div>
                        </div>
                        <?php foreach( $problems as $table_name => $problem ): ?>
                            <?php if( wpfval( $problem, 'fields' ) ): ?>
                                <?php foreach( $problem['fields'] as $problem_fields ): ?>
                                    <div style="display: flex; flex-direction: row; justify-content: space-between; padding: 5px 10px; background-color: #ffeffa; color: #aa0000; margin-bottom: 3px;">
                                        <div style="width: 30%; font-weight: 600;"><?php _e( 'Table:', 'wpforo' ); ?><?php echo $table_name; ?></div>
                                        <div style="text-align: left; flex-grow: 1;"><?php _e( 'Missing fields: ', 'wpforo' ); ?> &nbsp;<code><?php echo implode( ', ', $problem_fields ); ?></code></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if( wpfval( $problem, 'keys' ) ): ?>
                                <?php foreach( $problem['keys'] as $problem_keys ): ?>
                                    <div style="display: flex; flex-direction: row; justify-content: space-between; padding: 5px 10px; background-color: #ffeffa; color: #aa0000; margin-bottom: 3px;">
                                        <div style="width: 30%; font-weight: 600;"><?php _e( 'Table:', 'wpforo' ); ?><?php echo $table_name; ?></div>
                                        <div style="text-align: left; flex-grow: 1;"><?php _e( 'Missing keys: ', 'wpforo' ); ?> &nbsp;<code><?php echo implode( ', ', $problem_keys ); ?></code></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if( wpfkey( $problem, 'exists' ) ): ?>
                                <?php if( $problem['exists'] ): ?>
                                    <div style="display: flex; flex-direction: row; justify-content: space-between; padding: 5px 10px; background-color: #ffeffa;; color: #aa0000; margin-bottom: 3px;">
                                        <div style="width: 30%; font-weight: 600;"><?php _e( 'Table:', 'wpforo' ); ?><?php echo $table_name; ?></div>
                                        <div style="text-align: left; flex-grow: 1;"><?php _e( 'Doesn\'t exists', 'wpforo' ); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div style="display:flex; flex-direction: row; justify-content: space-between; border-top: 1px dashed #cccccc; padding-top: 10px; text-align: right; margin-top: 10px; font-weight: normal; font-size: 13px;">
                            <?php $update_db_url = wp_nonce_url( admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'tools' ) . '&tab=tables&wpfaction=database_update' ), 'wpforo_update_database' ); ?>
                            <div>
                                <a href="<?php echo esc_url( (string) $update_db_url ); ?>" class="button button-large" style="font-size:12px;">
                                    <?php _e( 'Recheck DB', 'wpforo' ); ?>
                                </a>
                            </div>
                            <div style="padding: 0 30px;">
                                <span id="wpf-debug-tables-solve-button" class="button button-primary button-large" style="font-size:14px;">
                                    <?php _e( 'Solve database problems', 'wpforo' ); ?>
                                </span>
                            </div>
                        </div>
                        <div style=" margin-top: 0px; font-weight: normal; font-size: 13px;">
                            <p style="font-weight: 600; font-size: 16px; margin-bottom: 2px;"><?php _e( 'IMPORTANT!', 'wpforo' ) ?></p>
                            <p style="font-size: 14px; line-height: 1.5; margin-top: 2px;"><?php printf(
                                    __( 'If the %s button doesn\'t solve the issues. Please use the SQl commands below in your hosting service cPanel > phpMyAdmin Database Manager > WordPress Database > SQL Tab. In case you\'re not familiar with hosting service tools, please contact to your hosting service support team and forward them this message with the SQL command.', 'wpforo' ),
                                    '<b style="color: #0085ba; font-size:13px;font-family: Courier">[' . __( 'Solve database problems', 'wpforo' ) . ']</b>'
                                ) ?></p>
                            <p style="font-size: 14px; font-weight: 600;"><?php _e( 'Problem fixer SQL commands:', 'wpforo' ) ?></p>
                            <pre style=" width: 100%; max-width: 1200px; display: block; padding: 10px; line-height: 1.4; border: 1px dashed #dd0000; background-color: #fffdea; color: #000000; overflow-x:auto;"><?php
                                if( ! empty( $problems ) ) {
                                    echo 'SET AUTOCOMMIT = 0;<br />';
                                    $SQL = wpforo_database_fixer( $problems );
                                    if( wpfval( $SQL, 'fields' ) ) {
                                        foreach( $SQL['fields'] as $query ) echo $query . '<br>';
                                    }
                                    if( wpfval( $SQL, 'keys' ) ) {
                                        foreach( $SQL['keys'] as $query ) echo $query . '<br>';
                                    }
                                    if( wpfval( $SQL, 'tables' ) ) {
                                        foreach( $SQL['tables'] as $query ) echo $query . '<br><br>';
                                    }
                                    if( wpfval( $SQL, 'data' ) ) {
                                        foreach( $SQL['data'] as $query ) echo $query . '<br><br>';
                                    }
                                    echo ';SET AUTOCOMMIT = 1;';
                                }
                                ?></pre>
                        </div>
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <tr>
                <td><h3 style="color: #00aa00; margin: 0; border-bottom: none; padding: 15px 0 30px 0"><span class="dashicons dashicons-shield" style="vertical-align: sub;"></span>&nbsp;<?php _e( 'No Problems Found in Database', 'wpforo' ) ?></h3></td>
            </tr>
        <?php endif; ?>
        <tr style="background-color: transparent;">
            <td>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <h3 style=" border-bottom: none;"><?php _e( 'Database Tables', 'wpforo' ) ?></h3>
                    <?php
                    $boardid = WPF()->board->get_current( 'boardid' );
                    printf(
                        '<a href="%1$s"><button type="button" style="background-color: #ec6262; color: white; padding: 5px 20px">%2$s</button></a>',
                        wp_nonce_url(
                            admin_url(
                                sprintf(
                                    'admin.php?page=%1$s&wpfaction=%2$s&boardid=%3$s',
                                    'wpforo-boards',
                                    'board_repair',
                                    $boardid
                                )
                            ),
                            'wpforo-board-repair-' . $boardid
                        ),
                        __( 'Repair', 'wpforo' )
                    ); ?>
                </div>
            </td>
        </tr>
        <tr>
            <td><?php foreach( WPF()->tables as $table ) {
                    wpforo_table_info( $table );
                } ?></td>
        </tr>
        </tbody>
    </table>
</div>


<?php
function wpforo_table_info( $table ) {
    $_table_exists = WPF()->db->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" );
    if( $_table_exists ) {
        $result = WPF()->db->get_results( "SHOW FULL COLUMNS FROM " . esc_sql( $table ), ARRAY_A );
        $count  = WPF()->db->get_var( "SELECT COUNT(*) FROM " . esc_sql( $table ) );
        $status = WPF()->db->get_row( "CHECK TABLE " . esc_sql( $table ), ARRAY_A );
        echo '<table class="wpf-main-table-data"><tr><td>';
        echo '<table class="wpf-table-data wpf-tbl">';
        echo '<tr><td colspan="6" style="background:#999999; color: #ffffff;font-size:14px; line-height:26px"><b>' . $table . '</b> &nbsp;|&nbsp; Rows: ' . $count . ' &nbsp;|&nbsp; Status: ' . $status['Msg_text'] . '</td></tr>';
        foreach( $result as $info ) {
            echo '<tr>';
            echo '<td>' . $info['Field'] . '</td>';
            echo '<td>' . $info['Type'] . '</td>';
            echo '<td>' . $info['Collation'] . '</td>';
            echo '<td>' . ( $info['Null'] == 'NO' ? '-' : 'NULL' ) . '</td>';
            echo '<td>' . $info['Key'] . '</td>';
            echo '<td>' . $info['Default'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</td>';
        echo '<td>';
        $result = WPF()->db->get_results( "SHOW INDEXES FROM " . esc_sql( $table ), ARRAY_A );

        echo '<table class="wpf-table-data">';
        echo '<tr><td colspan="6" style="background:#BBBBBB; color: #ffffff;font-size:14px; line-height:26px"><b>Indexes</b></td></tr>';
        $indexes = [];
        foreach( $result as $info ) {
            $indexes[ $info['Key_name'] ]['Key_name']      = $info['Key_name'];
            $indexes[ $info['Key_name'] ]['Column_name'][] = $info['Column_name'];
            $indexes[ $info['Key_name'] ]['Non_unique']    = $info['Non_unique'];
            $indexes[ $info['Key_name'] ]['Index_type']    = $info['Index_type'];
        }
        foreach( $indexes as $info ) {
            echo '<tr>';
            echo '<td>' . $info['Key_name'] . '</td>';
            echo '<td>' . implode( ', ', $info['Column_name'] ) . '</td>';
            echo '<td>' . ( $info['Non_unique'] ? '0' : 'Un' ) . '</td>';
            echo '<td>' . $info['Index_type'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '</td>';
        echo '</tr>';
        echo '</table>';
    } else {
        echo '<h3>Table <span style="color: red;">' . $table . '</span> doesn\'t exist</h3>';
    }
}
