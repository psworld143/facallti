<?php
// FTP Account Configuration
// Add your FTP hosting account credentials here

return [
    'default' => [
        'host' => 'ftp.seait-edu.ph',
        'username' => 'sms@sms.seait-edu.ph',
        'password' => '020894Ftp25*_',
        'port' => 21,
        'passive' => true,
        'ssl' => false,
        'timeout' => 30
    ],
    'sms' => [
        'host' => 'ftp.seait-edu.ph',
        'username' => 'sms@sms.seait-edu.ph',
        'password' => '020894Ftp25*_',
        'port' => 21,
        'passive' => true,
        'ssl' => false,
        'timeout' => 30
    ],
    
    // You can add multiple FTP accounts here
    'backup' => [
        'host' => 'backup.seait-edu.ph',
        'username' => 'backup_user',
        'password' => 'backup_password_here',
        'port' => 21,
        'passive' => true,
        'ssl' => false,
        'timeout' => 30
    ]
];
?>
