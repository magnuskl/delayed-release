<div class="wrap">
    <h2>Delayed Release Settings</h2>
    <p>
        Choose a category and a delay. The category will be removed from all
        posts to which it applies as soon as the delay, measured from the
        publishing time, has expired. Member content should be defined in terms
        of this category through the relevant plugin, such as WooCommerce
        Memberships.
    </p>

    <form action="options.php" method=post>
<?php
settings_fields( 'delayed_release' );
do_settings_sections( 'delayed_release' );
submit_button();
?>
    </form>
</div>
