<h1>Backupfiles - Cpalexh</h1>



<?php var_dump(scandir(wp_upload_dir()['basedir'])); ?>

<div class="wrap">
    <h2>My Plugin Settings</h2>
    <form method="post" action="options.php">
        <?php settings_fields('my_plugin_options_group'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Select Options:</th>
                <td>
                    <?php
                    $options = get_option('my_plugin_options');
                    $selected_options = isset($options['multiselect']) ? $options['multiselect'] : array();
                    ?>
                    <select name="my_plugin_options[multiselect][]" id="my_plugin_multiselect" multiple="multiple">
                        <option value="option1" <?php echo in_array('option1', $selected_options) ? 'selected' : ''; ?>>Option 1</option>
                        <option value="option2" <?php echo in_array('option2', $selected_options) ? 'selected' : ''; ?>>Option 2</option>
                        <option value="option3" <?php echo in_array('option3', $selected_options) ? 'selected' : ''; ?>>Option 3</option>
                        <option value="option4" <?php echo in_array('option4', $selected_options) ? 'selected' : ''; ?>>Option 4</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>