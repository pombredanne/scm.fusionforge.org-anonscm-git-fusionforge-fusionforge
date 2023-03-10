/**
 * MantisBT Plugin
 *
 * Copyright 2011, Franck Villaume - Capgemini
 * Copyright 2011-2012,2014,2021, Franck Villaume - TrivialDev
 * http://fusionforge.org
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/*! MantisBTInitController
 * @param groupId the current FusionForge groupID
 */
var MantisBTInitController = function(params)
{
	this.params	= params;
	this.bindControls();
};

var MantisBTAdminViewController = function(params)
{
	this.params	= params;
	this.bindControls();
	this.initCheckboxGlobalConf();
};

MantisBTInitController.prototype =
{
	/*! Binds the controls to the actions
	 */
	bindControls: function() {
		this.params.checkboxCreate.click(jQuery.proxy(this, "toggleInputName"));
		this.params.checkboxGlobalConf.click(jQuery.proxy(this, "toggleAllInput"));
	},

	/*! disable name input
	 */
	toggleInputName: function() {
		if (!this.params.checkboxCreate.is(":checked")) {
			this.params.inputName.attr('disabled',false);
		} else {
			this.params.inputName.attr('disabled',true);
		}
	},

	toggleAllInput: function() {
		if (!this.params.checkboxGlobalConf.is(":checked")) {
			this.params.inputUrl.attr('disabled',false);
			this.params.inputUser.attr('disabled',false);
			this.params.inputPassword.attr('disabled',false);
		} else {
			this.params.inputUrl.attr('disabled',true);
			this.params.inputUser.attr('disabled',true);
			this.params.inputPassword.attr('disabled',true);
		}
	},
}

MantisBTAdminViewController.prototype =
{
	/*! Binds the controls to the actions
	 */
	bindControls: function() {
		if (typeof(this.params.checkboxGlobalConf) != "undefined") {
			this.params.checkboxGlobalConf.click(jQuery.proxy(this, "toggleAllInput"));
		}
	},

	initCheckboxGlobalConf: function() {
		if (typeof(this.params.checkboxGlobalConf) != "undefined") {
			if (!this.params.checkboxGlobalConf.is(":checked")) {
				this.params.inputUrl.attr('disabled',false);
				this.params.inputUser.attr('disabled',false);
				this.params.inputPassword.attr('disabled',false);
			} else {
				this.params.inputUrl.attr('disabled',true);
				this.params.inputUser.attr('disabled',true);
				this.params.inputPassword.attr('disabled',true);
			}
		}
	},

	toggleAllInput: function() {
		if (!this.params.checkboxGlobalConf.is(":checked")) {
			this.params.inputUrl.attr('disabled',false);
			this.params.inputUser.attr('disabled',false);
			this.params.inputPassword.attr('disabled',false);
		} else {
			this.params.inputUrl.attr('disabled',true);
			this.params.inputUser.attr('disabled',true);
			this.params.inputPassword.attr('disabled',true);
		}
	},
}
