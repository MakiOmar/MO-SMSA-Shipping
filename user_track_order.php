<?php
get_header();

// Initialize API handler
require_once plugin_dir_path(__FILE__) . 'includes/class-mo-shipping-api.php';
$api = new MO_Shipping_API();

$arr = $api->track_shipment(sanitize_text_field($_GET['awb_no']), 'EN');

    if (isset($arr['trackingDetailsList']))
    {
?> 
                    <div class="table_con">
                     <h2><?php echo esc_html('Tracking Information for WayBill Number '.sanitize_text_field($_GET['awb_no'])); ?></h2> 
                    <table class="ord_table1">
                    <tr>
                    <th>Location</th>
                    <th>Date</th>
                    <th>Activity</th>
                    </tr>

               
                <?php
        foreach ($arr['trackingDetailsList'] as $roww)
        {
            echo '<tr>';
            echo '<td>' . $roww['office'] . ',' . $roww['countryCode'] . '</td>';
            echo '<td>' . $roww['eventTime'] . '</td>';
            echo '<td>' . $roww['eventDesc'] . '</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }

     else
    {
        ?>
       <h3><center><?php echo esc_html('Still Order Not Picked-Up by SMSA.');?></center></h3>
      
       <?php exit;
    }

}
else
{
    ?>
        <h3><?php echo esc_html('Please check your SMSA account credentials.');?></h3>";
    <?php
    exit;

}

get_footer();
?>
<style>
    .table_con
    {
        margin: 5% 10%
    }
    </style>
