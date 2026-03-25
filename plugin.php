<?php
return array(
    'id'          => 'ticket:duplicator',
    'version'     => '1.0.0',
    'name'        => /* trans */ 'Ticket Duplicator',
    'author'      => 'ChesnoTech',
    'description' => /* trans */ 'Adds a Duplicate button to the ticket view. Bulk-create identical copies of any ticket with a single click.',
    'url'         => 'https://github.com/ChesnoTech/osTicket-ticket-duplicator',
    'plugin'      => 'class.TicketDuplicatorPlugin.php:TicketDuplicatorPlugin',
);
