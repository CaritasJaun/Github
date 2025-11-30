<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Combined Custom Fields Helper
 * - Keeps existing helper API and table names from your project
 * - Upgrades saveCustomFields() to be belongs-aware and schema-safe
 * - Leaves all other functions untouched (except minor docblocks/formatting)
 */

/* =============================================================
 * UPGRADED: saveCustomFields()
 * Backward compatible: saveCustomFields($post, $userID)
 * Optional slug:       saveCustomFields($post, $userID, 'employee')
 * Uses your table names: custom_fields_values, custom_field
 * ============================================================= */
if (!function_exists('saveCustomFields')) {
    /**
     * Persist custom field values for a specific record.
     *
     * @param array       $post     Array of field_id => value pairs.
     * @param int         $userID   Related record identifier (relid).
     * @param string|null $belongs  Optional slug identifying the owner form (e.g., 'employee').
     * @return void
     */
    function saveCustomFields($post, $userID, $belongs = null)
    {
        $CI =& get_instance();

        if (!is_array($post)) {
            $post = [];
        }

        // Keep your existing schema/table names
        $valuesTable = 'custom_fields_values';
        $fieldTable  = 'custom_field';

        // Normalize belongs
        $belongs = ($belongs !== null && $belongs !== '') ? (string)$belongs : null;

        // Discover columns so we can adapt to schema differences
        $valuesColumns    = $CI->db->list_fields($valuesTable);
        $hasBelongsColumn = in_array('belongs_to', $valuesColumns, true);

        // If a belongs slug is provided, figure out which column in custom_field stores it
        $allowedFieldIds = null;
        if ($belongs !== null) {
            $fieldColumns = $CI->db->list_fields($fieldTable);
            $slugColumn   = null;
            foreach (['form_to', 'belongs_to', 'form_slug', 'field_to', 'rel_type'] as $candidate) {
                if (in_array($candidate, $fieldColumns, true)) {
                    $slugColumn = $candidate;
                    break;
                }
            }

            if ($slugColumn !== null) {
                $CI->db->select('id');
                $CI->db->from($fieldTable);
                $CI->db->where($slugColumn, $belongs);
                $allowedFieldIds = array_map('intval', array_column($CI->db->get()->result_array(), 'id'));
            }
        }

        $persistedFieldIds = [];

        // Upsert posted values
        foreach ($post as $fieldId => $fieldValue) {
            $fieldId = (int)$fieldId;

            // If a belongs slug is provided and we resolved allowed ids, skip mismatches
            if ($belongs !== null && is_array($allowedFieldIds) && !in_array($fieldId, $allowedFieldIds, true)) {
                continue;
            }

            $persistedFieldIds[] = $fieldId;

            // Flatten arrays (checkbox groups, multi-selects)
            if (is_array($fieldValue)) {
                $fieldValue = implode(',', $fieldValue);
            }

            // Does a row already exist for this relid + field_id (+ optional belongs)?
            $CI->db->where('relid', $userID);
            $CI->db->where('field_id', $fieldId);
            if ($belongs !== null && $hasBelongsColumn) {
                $CI->db->where('belongs_to', $belongs);
            }
            $existing = $CI->db->get($valuesTable)->row_array();

            if ($existing) {
                $update = ['value' => $fieldValue];
                if ($belongs !== null && $hasBelongsColumn) {
                    $update['belongs_to'] = $belongs;
                }

                $CI->db->where('id', (int)$existing['id']);
                $CI->db->update($valuesTable, $update);
            } else {
                $insert = [
                    'relid'    => $userID,
                    'field_id' => $fieldId,
                    'value'    => $fieldValue,
                ];
                if ($belongs !== null && $hasBelongsColumn) {
                    $insert['belongs_to'] = $belongs;
                }

                $CI->db->insert($valuesTable, $insert);
            }
        }

        // ── Safety: if nothing was actually posted/persisted, don't run a delete sweep.
        if (empty($persistedFieldIds)) {
            return;
        }

        // Cleanup: remove orphaned values for this record (scoped safely)
        $CI->db->where('relid', $userID);

        if ($belongs !== null) {
            if ($hasBelongsColumn) {
                // Precise when schema supports belongs_to on values table
                $CI->db->where('belongs_to', $belongs);
            } else {
                // Fallback: scope using field ids for this slug (if known)
                if (is_array($allowedFieldIds)) {
                    if (empty($allowedFieldIds)) {
                        // Nothing to delete if no fields are defined for this slug
                        return;
                    }
                    $CI->db->where_in('field_id', $allowedFieldIds);
                } elseif (empty($post)) {
                    // No safe scope available; avoid destructive delete
                    return;
                }
            }
        }

        if (!empty($persistedFieldIds)) {
            $CI->db->where_not_in('field_id', array_unique($persistedFieldIds));
        }

        $CI->db->delete($valuesTable);
    }
}

/* =============================================================
 * EXISTING RENDER + QUERY HELPERS (unchanged behaviour)
 * ============================================================= */
if (!function_exists('render_custom_Fields')) {
function render_custom_Fields($belongs_to, $branch_id = null, $edit_id = false, $col_sm = null)
{
    $CI = &get_instance();
    if (empty($branch_id)) {
        $branch_id = $CI->application_model->get_branch_id();
    }
    $CI->db->from('custom_field');
    $CI->db->where('status', 1);
    $CI->db->where('form_to', $belongs_to);
    $CI->db->where('branch_id', $branch_id);
    $CI->db->order_by('field_order','asc');
    $fields = $CI->db->get()->result_array();
    if (count($fields)) {
        $html = '';
        foreach ($fields as $field_key => $field) {
            $fieldLabel = ucfirst($field['field_label']);
            $fieldType  = $field['field_type'];
            $bsColumn   = $field['bs_column'];
            $required   = $field['required'];
            $formTo     = $field['form_to'];
            $fieldID    = $field['id'];

            if ($bsColumn == '' || $bsColumn == 0) {
                $bsColumn = 12;
            }
            $value = $field['default_value'];

            if ($edit_id !== false) {
                $return = get_custom_field_value($edit_id, $fieldID, $formTo);
                if (!empty($return)) {
                    $value = $return;
                }
            }

            if (isset($_POST['custom_fields'][$formTo][$fieldID])) {
                $value = $_POST['custom_fields'][$formTo][$fieldID];
            }

            if ($fieldType != 'checkbox') {
                $html .= '<div class="col-md-' . $bsColumn . ' mb-sm"><div class="form-group">';
                $html .= '<label class="control-label">' . $fieldLabel . ($required == 1 ? ' <span class="required">*</span>' : '') . '</label>';
                if ($fieldType == 'text' || $fieldType == 'number' || $fieldType == 'email') {
                    $html .= '<input type="' . $fieldType . '" class="form-control" autocomplete="off" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="' . $value . '" />';
                }
                if ($fieldType == 'textarea') {
                    $html .= '<textarea type="' . $fieldType . '" class="form-control" name="custom_fields[' . $formTo . '][' . $fieldID . ']">' . $value . '</textarea>';
                }
                if ($fieldType == 'dropdown') {
                    $html .= '<select class="form-control" data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity" name="custom_fields[' . $formTo . '][' . $fieldID . ']">';
                    $html .= dropdownField($field['default_value'], $value);
                    $html .= '</select>';
                }
                if ($fieldType == 'date') {
                    $html .= '<input type="text" class="form-control" data-plugin-datepicker autocomplete="off" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="' . $value . '" />';
                }
                $html .= '<span class="error">' . form_error('custom_fields[' . $formTo . '][' . $fieldID . ']') . '</span>';
                $html .= '</div></div>';
            } else {
                // FIX: ensure checkbox posts under custom_fields[...] and reliably saves 0/1
                $html .= '<div class="col-md-' . $bsColumn . ' mb-sm"><div class="checkbox-replace">';
                $html .= '<label class="i-checks">';
                // Hidden 0 first, then checkbox 1 (checked overrides hidden)
                $html .= '<input type="hidden" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="0" />';
                $html .= '<input type="checkbox" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="1" ' . ((string)$value === '1' ? 'checked' : '') . ' ><i></i>';
                $html .= $fieldLabel;
                $html .= '</label>';
                $html .= '</div></div>';
            }
        }
        return $html;
    }
}
}

if (!function_exists('dropdownField')) {
function dropdownField($default, $value)
{
    $options = explode(',', $default);
    $input   = '<option value="">Select</option>';
    foreach ($options as $option_key => $option_value) {
        $v = slugify($option_value);
        $input .= '<option value="' . $v . '" ' . ($v == $value ? 'selected' : '') . '>' . ucfirst($option_value) . '</option>';
    }
    return $input;
}
}

if (!function_exists('getCustomFields')) {
function getCustomFields($belong_to, $branchID = '')
{
    $CI = &get_instance();
    if (empty($branchID)) {
        $branchID = $CI->application_model->get_branch_id();
    }
    $CI->db->from('custom_field');
    $CI->db->where('status', 1);
    $CI->db->where('form_to', $belong_to);
    $CI->db->where('branch_id', $branchID);
    $CI->db->order_by('field_order','asc');
    $fields = $CI->db->get()->result_array();
    return $fields;
}
}

if (!function_exists('get_custom_field_value')) {
function get_custom_field_value($rel_id, $field_id, $belongs_to)
{
    $CI = &get_instance();
    $CI->db->select('custom_fields_values.value');
    $CI->db->from('custom_field');
    $CI->db->join('custom_fields_values', 'custom_fields_values.field_id = custom_field.id and custom_fields_values.relid = ' . (int)$rel_id, 'inner');
    $CI->db->where('custom_field.form_to', $belongs_to);
    $CI->db->where('custom_fields_values.field_id', (int)$field_id);
    $row = $CI->db->get()->row_array();
    if (empty($row)) {
        return null;
    } else {
        return $row['value'];
    }
}
}

if (!function_exists('custom_form_table')) {
function custom_form_table($belong_to, $branch_id, $page = false)
{
    $CI = &get_instance();
    $CI->db->from('custom_field');
    $CI->db->where('status', 1);
    $CI->db->where('form_to', $belong_to);
    if ($page == false) {
        $CI->db->where('show_on_table', 1);
    }
    $CI->db->where('branch_id', $branch_id);
    $CI->db->order_by('field_order','asc');
    $fields = $CI->db->get()->result_array();
    return $fields;
}
}

if (!function_exists('get_table_custom_field_value')) {
function get_table_custom_field_value($field_id, $rel_id)
{
    $CI = &get_instance();
    $CI->db->from('custom_fields_values');
    $CI->db->where('relid', (int)$rel_id);
    $CI->db->where('field_id', (int)$field_id);
    $row = $CI->db->get()->row_array();
    if (empty($row)) {
        return null;
    } else {
        return $row['value'];
    }
}
}

/* ====================== ONLINE ADMISSION ====================== */
if (!function_exists('render_online_custom_fields')) {
function render_online_custom_fields($belongs_to, $branch_id = null, $edit_id = false, $col_sm = null)
{
    $CI = &get_instance();
    if (empty($branch_id)) {
        $branch_id = $CI->application_model->get_branch_id();
    }
    if ($edit_id == false) {
        $CI->db->select('custom_field.*, if(oaf.status is null, custom_field.status, oaf.status) as fstatus, if(oaf.required is null, custom_field.required, oaf.required) as required');
        $CI->db->from('custom_field');
        $CI->db->join('online_admission_fields as oaf', 'oaf.fields_id = custom_field.id and oaf.system = 0 and oaf.branch_id = ' . (int)$branch_id, 'left');
        $CI->db->where('custom_field.status', 1);
        $CI->db->where('custom_field.form_to', $belongs_to);
        $CI->db->where('custom_field.branch_id', $branch_id);
        $CI->db->order_by('custom_field.field_order','asc');
        $fields = $CI->db->get()->result_array();
    } else {
        $CI->db->select('*, status as fstatus');
        $CI->db->from('custom_field');
        $CI->db->where('form_to', $belongs_to);
        $CI->db->where('branch_id', $branch_id);
        $CI->db->order_by('field_order','asc');
        $fields = $CI->db->get()->result_array();
    }

    if (count($fields)) {
        $html = '';
        foreach ($fields as $field_key => $field) {
            if ($field['fstatus'] == 1) {
                $fieldLabel = ucfirst($field['field_label']);
                $fieldType  = $field['field_type'];
                $bsColumn   = $field['bs_column'];
                $required   = $field['required'];
                $formTo     = $field['form_to'];
                $fieldID    = $field['id'];

                if ($bsColumn == '' || $bsColumn == 0) {
                    $bsColumn = 12;
                }
                $value = $field['default_value'];

                if ($edit_id !== false) {
                    $return = get_online_custom_field_value($edit_id, $fieldID, $formTo);
                    if (!empty($return)) {
                        $value = $return;
                    }
                }

                if (isset($_POST['custom_fields'][$formTo][$fieldID])) {
                    $value = $_POST['custom_fields'][$formTo][$fieldID];
                }

                if ($fieldType != 'checkbox') {
                    $html .= '<div class="col-md-' . $bsColumn . ' mb-sm"><div class="form-group">';
                    $html .= '<label class="control-label">' . $fieldLabel . ($required == 1 ? ' <span class="required">*</span>' : '') . '</label>';
                    if ($fieldType == 'text' || $fieldType == 'number' || $fieldType == 'email') {
                        $html .= '<input type="' . $fieldType . '" class="form-control" autocomplete="off" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="' . $value . '" />';
                    }
                    if ($fieldType == 'textarea') {
                        $html .= '<textarea type="' . $fieldType . '" class="form-control" name="custom_fields[' . $formTo . '][' . $fieldID . ']">' . $value . '</textarea>';
                    }
                    if ($fieldType == 'dropdown') {
                        $html .= '<select class="form-control" data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity" name="custom_fields[' . $formTo . '][' . $fieldID . ']">';
                        $html .= dropdownField($field['default_value'], $value);
                        $html .= '</select>';
                    }
                    if ($fieldType == 'date') {
                        $html .= '<input type="text" class="form-control" data-plugin-datepicker autocomplete="off" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="' . $value . '" />';
                    }
                    $html .= '<span class="error">' . form_error('custom_fields[' . $formTo . '][' . $fieldID . ']') . '</span>';
                    $html .= '</div></div>';
                } else {
                    // FIX: ensure checkbox posts under custom_fields[...] and reliably saves 0/1
                    $html .= '<div class="col-md-' . $bsColumn . ' mb-sm"><div class="checkbox-replace">';
                    $html .= '<label class="i-checks">';
                    $html .= '<input type="hidden" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="0" />';
                    $html .= '<input type="checkbox" name="custom_fields[' . $formTo . '][' . $fieldID . ']" value="1" ' . ((string)$value === '1' ? 'checked' : '') . ' ><i></i>';
                    $html .= $fieldLabel;
                    $html .= '</label>';
                    $html .= '</div></div>';
                }
            }
        }
        return $html;
    }
}
}

if (!function_exists('saveCustomFieldsOnline')) {
function saveCustomFieldsOnline($post, $userID)
{
    $CI = &get_instance();
    foreach ($post as $key => $value) {
        $insertData = array(
            'field_id' => $key,
            'relid'    => $userID,
            'value'    => $value,
        );
        $CI->db->where('relid', $userID);
        $CI->db->where('field_id', $key);
        $query = $CI->db->get('custom_fields_online_values');
        if ($query->num_rows() > 0) {
            $results = $query->row();
            $CI->db->where('id', $results->id);
            $CI->db->update('custom_fields_online_values', $insertData);
        } else {
            $CI->db->insert('custom_fields_online_values', $insertData);
        }
    }
}
}

if (!function_exists('get_online_custom_field_value')) {
function get_online_custom_field_value($rel_id, $field_id, $belongs_to)
{
    $CI = &get_instance();
    $CI->db->select('custom_fields_online_values.value');
    $CI->db->from('custom_field');
    $CI->db->join('custom_fields_online_values', 'custom_fields_online_values.field_id = custom_field.id and custom_fields_online_values.relid = ' . (int)$rel_id, 'inner');
    $CI->db->where('custom_field.form_to', $belongs_to);
    $CI->db->where('custom_fields_online_values.field_id', (int)$field_id);
    $row = $CI->db->get()->row_array();
    if (empty($row)) {
        return null;
    } else {
        return $row['value'];
    }
}
}

if (!function_exists('get_online_custom_table_custom_field_value')) {
function get_online_custom_table_custom_field_value($field_id, $rel_id)
{
    $CI = &get_instance();
    $CI->db->from('custom_fields_online_values');
    $CI->db->where('relid', (int)$rel_id);
    $CI->db->where('field_id', (int)$field_id);
    $row = $CI->db->get()->row_array();
    if (empty($row)) {
        return null;
    } else {
        return $row['value'];
    }
}
}

if (!function_exists('getOnlineCustomFields')) {
function getOnlineCustomFields($belong_to, $branchID = '')
{
    $CI = &get_instance();
    if (empty($branchID)) {
        $branchID = $CI->application_model->get_branch_id();
    }
    $CI->db->select('custom_field.*, if(oaf.status is null, custom_field.status, oaf.status) as fstatus, if(oaf.required is null, custom_field.required, oaf.required) as required');
    $CI->db->from('custom_field');
    $CI->db->join('online_admission_fields as oaf', 'oaf.fields_id = custom_field.id and oaf.system = 0 and oaf.branch_id = ' . (int)$branchID, 'left');
    $CI->db->where('custom_field.status', 1);
    $CI->db->where('custom_field.form_to', $belong_to);
    $CI->db->where('custom_field.branch_id', $branchID);
    $CI->db->order_by('custom_field.field_order','asc');
    $fields = $CI->db->get()->result_array();
    return $fields;
}
}
