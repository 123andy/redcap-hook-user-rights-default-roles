<?php
if($hook_event == "redcap_user_rights"){
	global $roles, $auth_meth_global, $user_rights_template_pid;

	$msgAlign 	= "center";
	$msgId 		= "actionMsg";

	if($user_rights_template_pid){
		$template_project_id = $user_rights_template_pid;
	}else{
		if ( defined('SUPER_USER') && SUPER_USER ) {
			$msgAlign = "left";
			displayMsg("In order to use user_rights_default_roles hook, you must specifiy a template project which contains the default template roles you wish to apply.  
				To configure your template project execute the following SQL where <b>\$template_pid</b> is the project ID for your template project. <br><br>
				<code>
				 INSERT INTO redcap_config (field_name,value) <br> VALUES ('user_rights_template_pid', <b>\$template_pid</b>)<br> ON DUPLICATE KEY UPDATE  value=<b>\$template_pid</b>; 
				</code>"
				, $msgId, $msgAlign, "yellow", "exclamation.png", 20000);	
		}else{
			displayMsg("Notify your REDCap admin that the user_rights_default_roles hook is not properly configured."
				, $msgId, $msgAlign, "yellow", "exclamation.png", 20000);
		}
	}

	// WARN THAT USERS ARE NOT ASSIGNED TO ROLES IF MORE THAN 3 USERS UNASSIGNED
	$project_users 	= UserRights::getRightsAllUsers();
	$assigned_users = array_filter($project_users, function($usr){
		return $usr["role_id"];
	});
	if(count($project_users) - count($assigned_users) >= 3){
		displayMsg("We recommend that users be assigned to roles. It is good security practice<br> to limit access of essential functionality to the users' defined role."
				, $msgId, $msgAlign, "yellow", "exclamation.png", 20000);
	}

	// print UserRights::renderUserRightsRolesTable();
	if(count($roles) == 0 && $template_project_id){
		applyTemplateRoles($template_project_id, $project_id);
		redirect($_SERVER['REQUEST_URI']. "&rolesApplied=1");
	}

	if(isset($_REQUEST["rolesApplied"])){
		displayMsg("Template roles have been added to your project."
			, $actionMsg, $msgAlign, "green", "tick.png", 20);	
	}

	?>
	<script>
	$(document).ready(function(){
		fixUserRights();
		callOutAddRoles();

		// / Create observer that maintains the sync going forward
		var observer = new MutationObserver(function(mutations) {
			fixUserRights();
			callOutAddRoles();
		});

		// Attach observer to target
		var target = $("#user_rights_roles_table_parent")[0];
		observer.observe(target,{
			childList:true
		});

		$(document).on('click', function(event) {
		  if (!$(event.target).closest('#<?php echo $msgId?>').length) {
		    $("#<?php echo $msgId ?>").fadeOut("slow" , function(){
		    	$(this).remove();
		    });
		  }
		});
	});

	//UNHIDE ASSIGN TO ROLE BUTTON FOR UNASSIGNED USERS
	function callOutAddRoles(){
		//CLONE THE HIDDEN TOOL TIP FOR ASSIGN TO ROLE, ADD IT TO THE TABLE WHERE APPROPRIATE
		$("#table-user_rights_roles_table tr").each(function(){
			var row = $(this);
			if( row.find("td:eq(0):contains('—')").length ){
				var username 	= row.find("td:eq(1) b").text();
				var tooltip 	= $("#tooltipBtnAssignRole").clone();
				tooltip.prop({id:"", class:"assignRole"});
				tooltip.click(function(){
					var thisoffset = $(this).offset();
					$('#userClickTooltip').show().css("top","-5000px").css("left","-5000px");
					$("#assignUserDropdownDiv").hide();
					$("#tooltipHiddenUsername").val(username);
					$("#assignUserDropdownDiv").show().css("top", thisoffset.top + 30 ).css("left",thisoffset.left);
					return false;
				});
				$(this).find("td:eq(0)").empty().append(tooltip);
			}
		});
		return;
	}

	//OVERIDE NEW USER ADD TABLE TO HIDE UNDESIRABLE "CUSTOM RIGHTS" FIELD
	function fixUserRights(){
		$("#addUsersRolesDiv div:contains('— OR —')").remove();
		var newuserclone = $("#new_username").closest("div").detach();

		var tempbox = $("<details><summary>Add user with custom rights</summary></details>");
		tempbox.append(newuserclone);
		$("#addUsersRolesDiv").append(tempbox);
	}
	</script>
	<style>
		/*OVER WRITE THE DEFAULT INLINE DISPLAY MESSAGE TO COME DOWN FROM THE TOP*/
		#<?php echo $msgId ?> { 
			max-width:initial !important;
			margin:0 auto !important;  
			padding:22px 25px 25px !important;
			text-align:<?php echo $msgAlign ?>;

			position:fixed; 
			width:80%;  
			top:-1px; 
			right:0; 
			left:0; 
			border-radius:0 0 8px 8px;
			display:none;
			z-index:99;
		}
		#addUsersRolesDiv{ padding:20px 10px !important; }
		#addUsersRolesDiv div:nth-child(2){
			margin-top:8px !important;
		}
		#addUsersRolesDiv details {
			margin-top:15px; 
			font-weight:Bold; 
			vertical-align: middle; 
		}
	</style>
	<?php
}

function applyTemplateRoles($source, $destination){
	$template_project_id 	= $source;
	$project_id 			= $destination;
	db_query("CREATE TEMPORARY TABLE template_roles AS SELECT * FROM redcap_user_roles WHERE project_id = $template_project_id;");
	db_query("UPDATE template_roles SET project_id = $project_id;");
	db_query("ALTER TABLE template_roles CHANGE COLUMN role_id role_id INT(10) NULL;");
	db_query("UPDATE template_roles SET role_id = NULL;");
	db_query("INSERT INTO redcap_user_roles SELECT * FROM template_roles;");
	db_query("DROP TEMPORARY TABLE template_roles;");
	return;
}










