/**
 * -----------------------------------------------------------------------------
 * Purpose: Scripts for admin views of Head Cleaner plugin for ClassicPress.
 * -----------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.txt.
 * -----------------------------------------------------------------------------
 * Copyright 2021, John Alarcon (Code Potent)
 * -----------------------------------------------------------------------------
 */

jQuery(document).ready(function($) {

	$('.codepotent-head-cleaner-details-link').on('click', function(e) {
		e.preventDefault();
		$('#'+this.dataset.id+'-example').toggle('fast', function() {});
	});

});
