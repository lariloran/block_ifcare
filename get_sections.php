<?php
require_once('../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);

$modinfo = get_fast_modinfo($courseid);
$sections = $modinfo->get_section_info_all(); 
$response = ['sections' => []]; 

foreach ($sections as $section) {
    if ($section->uservisible) {
        $sectionname = get_section_name($courseid, $section->section);
        $response['sections'][] = [
            'value' => $section->section,
            'name' => $sectionname
        ];
    }
}

echo json_encode($response);
exit;
