<?php
return array(
    'id'          => 'chesnotech:ticket-duplicator',
    'version'     => '1.3.1',
    'name'        => /* trans */ 'Ticket Duplicator',
    'author'      => 'ChesnoTech',
    'description' => /* trans */ 'Adds a Duplicate button to the ticket view page. Creates a new ticket with the same metadata and first internal note for the same end-user.',
    'url'         => 'https://chesnotech.com',
    'ost_version' => '1.18',
    'plugin'      => 'class.TicketDuplicatorPlugin.php:TicketDuplicatorPlugin',
);
