<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Master_model bukan kelas MY_*, jadi tidak dimuat otomatis oleh CI3.
require_once APPPATH . 'models/Master_model.php';

class Action_team_model extends Master_model
{
    protected $table = 'action_teams';
    protected $order_by = 'urutan';
    protected $asc = true;
}