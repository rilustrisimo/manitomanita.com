<?php
require_once(dirname(__FILE__) . '/../../../wp-load.php');

$users = new Users();
$batch_size = 100; // Number of records to process per batch
$offset = 0; // Starting point for each batch
$list = array();

// Open CSV file for writing
$csv_file = fopen('user_data_group.csv', 'w');
fputcsv($csv_file, ['email', 'fullname']); // CSV headers

do {
    // Fetch users in batches with createQuery3
    $getusers = $users->createQuery3('groups', array(), $batch_size, $offset);
    $batch_users = wp_list_pluck($getusers, 'ID');

    if (empty($batch_users)) {
        break; // Exit if no more users to process
    }

    // Process each user in the current batch
    foreach ($batch_users as $id) {
        $email = get_field('your_email', $id);
        $name = get_field('your_name', $id);

        $list[] = array(
            'email' => $email,
            'fullname' => $name,
        );

        // Write each user data to CSV
        fputcsv($csv_file, [$email, $name]);
    }
    
    echo $offset.'<br>';

    $offset += $batch_size; // Move to the next set of users
} while (count($batch_users) == $batch_size); // Continue if full batch was processed

// Close the CSV file
fclose($csv_file);

echo "CSV file 'user_data_group.csv' created successfully!";
?>
