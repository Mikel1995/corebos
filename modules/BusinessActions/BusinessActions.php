<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';
include_once 'vtlib/Vtiger/Utils/StringTemplate.php';
include_once 'vtlib/Vtiger/LinkData.php';


class BusinessActions extends CRMEntity {
	public $db;
	public $log;

	public $table_name = 'vtiger_businessactions';
	public $table_index = 'businessactionsid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	const IGNORE_MODULE = -1;
	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_businessactionscf', 'businessactionsid');
	// related_tables variable should define the association (relation) between dependent tables
	// FORMAT: related_tablename => array(related_tablename_column[, base_tablename, base_tablename_column[, related_module]] )
	// Here base_tablename_column should establish relation with related_tablename_column
	// NOTE: If base_tablename and base_tablename_column are not specified, it will default to modules (table_name, related_tablename_column)
	// Uncomment the line below to support custom field columns on related lists
	// var $related_tables = array('vtiger_MODULE_NAME_LOWERCASEcf' => array('MODULE_NAME_LOWERCASEid', 'vtiger_MODULE_NAME_LOWERCASE',
	// 'MODULE_NAME_LOWERCASEid', 'MODULE_NAME_LOWERCASE'));

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_businessactions', 'vtiger_businessactionscf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_businessactions' => 'businessactionsid',
		'vtiger_businessactionscf' => 'businessactionsid',
	);

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'businessactions_no' => array('businessactions' => 'businessactions_no'),
		'linklabel' => array('businessactions' => 'linklabel'),
		'linktype' => array('businessactions' => 'elementtype_action'),
		'module_list' => array('businessactions' => 'module_list'),
		'active' => array('businessactions' => 'active'),
		'Assigned To' => array('crmentity' => 'smownerid'),
	);
	public $list_fields_name = array(
		/* Format: Field Label => fieldname */
		'businessactions_no' => 'businessactions_no',
		'linklabel' => 'linklabel',
		'linktype' => 'elementtype_action',
		'module_list' => 'module_list',
		'active' => 'active',
		'Assigned To' => 'assigned_user_id',
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'businessactions_no';

	// For Popup listview and UI type support
	public $search_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'businessactions_no' => array('businessactions' => 'businessactions_no'),
		'linklabel' => array('businessactions' => 'linklabel'),
		'linktype' => array('businessactions' => 'elementtype_action'),
		'module_list' => array('businessactions' => 'module_list'),
		'active' => array('businessactions' => 'active'),
		'Assigned To' => array('crmentity' => 'smownerid'),
	);
	public $search_fields_name = array(
		/* Format: Field Label => fieldname */
		'businessactions_no' => 'businessactions_no',
		'linklabel' => 'linklabel',
		'linktype' => 'elementtype_action',
		'module_list' => 'module_list',
		'active' => 'active',
		'Assigned To' => 'assigned_user_id',
	);

	// For Popup window record selection
	public $popup_fields = array('businessactions_no');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'businessactions_no';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'businessactions_no';

	// Required Information for enabling Import feature
	public $required_fields = array('businessactions_no' => 1);

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'businessactions_no';
	public $default_sort_order = 'ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime', 'businessactions_no');

	public function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		if ($event_type == 'module.postinstall') {
			// TODO Handle post installation actions
			$this->setModuleSeqNumber('configure', $modulename, 'bact-', '0000001');
		} elseif ($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
		} elseif ($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// TODO Handle actions after this module is updated.
		}
	}

	public static function getAllByType($tabid, $type = false, $parameters = false, $userid = null) {
		global $adb, $current_user, $currentModule;
		// self::__initSchema();

		$multitype = false;
		$orderby = ' order by elementtype_action,sequence'; //MSL
		if ($type) {
			// Multiple link type selection?
			if (is_array($type)) {
				$multitype = true;
				if ($tabid === self::IGNORE_MODULE) {
					$sql = 'SELECT * FROM vtiger_businessactions WHERE elementtype_action IN ('.
						Vtiger_Utils::implodestr('?', count($type), ',') .') ';
					$params = $type;
					$permittedTabIdList = getPermittedModuleIdList();
					if (count($permittedTabIdList) > 0 && $current_user->is_admin !== 'on') {
						$sql .= ' and tabid IN ('.
							Vtiger_Utils::implodestr('?', count($permittedTabIdList), ',').')';
						$params[] = $permittedTabIdList;
					}
					if (!empty($currentModule)) {
						$sql .= ' and ((onlyonmymodule and module_list=?) or !onlyonmymodule) ';
						$params[] = getTabid($currentModule);
					}
					$result = $adb->pquery($sql . $orderby, array($adb->flatten_array($params)));
				} else {
					$result = $adb->pquery(
						'SELECT * FROM vtiger_businessactions WHERE module_list=? AND elementtype_action IN ('.
						Vtiger_Utils::implodestr('?', count($type), ',') .')' . $orderby,
						array($tabid, $adb->flatten_array($type))
					);
				}
			} else {
				// Single link type selection
				if ($tabid === self::IGNORE_MODULE) {
					$result = $adb->pquery('SELECT * FROM vtiger_businessactions WHERE elementtype_action=?' . $orderby, array($type));
				} else {
					echo "string";
					$result = $adb->pquery('SELECT * FROM vtiger_businessactions WHERE module_list=? AND elementtype_action=?' . $orderby, array($tabid, $type));
				}
			}
		} else {
			$result = $adb->pquery('SELECT * FROM vtiger_businessactions WHERE module_list=?' . $orderby, array($tabid));
		}

		//Below to implement search by user, role and group 
		//PS: here is implemented (more or less) the logic. 
		$SET1 = array();
		$SET2 = array();

		$multitype = false;
		if ($userid == null) {
			$bauserid = $current_user->id;
		} else {
			$bauserid = $userid;
		}

		$join = ' FROM vtiger_businessactions INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_businessactions.businessactionsid';

		$select = 'select *'.$join;

		$where_active = ' where vtiger_crmentity.deleted=0 and active = 1 and module_list like "%'.$moduleName.'%" ';

		$where_inactive = ' where vtiger_crmentity.deleted=0 and active=0 and module_list like"%'.$moduleName.'%"';

		$userrole = $adb->convert2sql(' inner join vtiger_user2role on vtiger_user2role.userid=?', array($bauserid));

		$sql_active = $select.$userrole.$where_active." and acrole like concat('%', vtiger_user2role.roleid, '%')";

		$slq_inactive = $select.$userrole.$where_inactive." and acrole like concat('%', vtiger_user2role.roleid, '%')";
		$result_active = $adb->query($sql_active);

		$result_active_array = $adb->fetch_array($result_active);
		if ($adb->num_rows($result_active)) {
			array_push($SET1, $adb->fetch_array($result_active));
		}

		$result_inactive = $adb->query($slq_inactive);
		if ($adb->num_rows($result_inactive)) {
			array_push($SET2, $adb->fetch_array($result_inactive));
		}
		$user = $adb->convert2sql(' and vtiger_crmentity.smownerid=?', array($bauserid));
		$sql_active=$select.$where_active.$user;
		$result_active = $adb->query($sql_active);

		$sql_inactive=$select.$where_inactive.$user;
		$result_inactive = $adb->query($sql_inactive);

		if ($adb->num_rows($result_active)) {
			array_push($SET1, $adb->fetch_array($result_active));
		}

		$result_inactive = $adb->query($slq_inactive);
		if ($adb->num_rows($result_inactive)) {
			array_push($SET2, $adb->fetch_array($result_inactive));
		}
		require_once 'include/utils/GetUserGroups.php';
		$UserGroups = new GetUserGroups();
		$UserGroups->getAllUserGroups($bauserid);
		if (count($UserGroups->user_groups)>0) {
			$groups=implode(',', $UserGroups->user_groups);
			$group = ' and vtiger_crmentity.smownerid in ( select userid from vtiger_users2group where groupid=? )';

			$sql_active = $select.$where_active.$group;
			$result_active = $adb->pquery($sql_active, array($groups));

			$sql_inactive = $select.$where_inactive.$group;
			$result_inactive = $adb->pquery($sql_inactive, array($groups));

			if ($adb->num_rows($result_active)) {
				array_push($SET1, $adb->fetch_array($result_active));
			}

			$result_inactive = $adb->query($slq_inactive);
			if ($adb->num_rows($result_inactive)) {
				array_push($SET2, $adb->fetch_array($result_inactive));
			}
		}
		$result = array_diff($SET1, $SET2);
		return $result;
	}

	/**
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// public function save_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle deleting related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function delete_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }
}
