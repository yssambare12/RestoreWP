/**
 * RestoreWP Admin JavaScript
 * Basic implementation without React for immediate functionality
 */

(function($) {
	'use strict';

	// Simple translation function fallback
	window.__ = window.__ || function(text, domain) {
		return text;
	};

	const RestoreWPAdmin = {
		init: function() {
			this.bindEvents();
			this.createTabs();
		},

		bindEvents: function() {
			$(document).on('click', '.restorewp-export-btn', this.handleExport);
			$(document).on('click', '.restorewp-import-btn', this.handleImport);
			$(document).on('click', '.restorewp-backup-delete', this.handleBackupDelete);
			$(document).on('change', '#restorewp-file-input', this.handleFileSelect);
		},

		createTabs: function() {
			const container = $('#restorewp-admin-root');
			if (!container.length) return;

			const activeTab = container.data('active-tab') || 'export';

			const html = `
				<div class="restorewp-admin-container">
					<div class="restorewp-header">
						<h1>
							<span class="dashicons dashicons-migrate"></span>
							RestoreWP
						</h1>
						<p>Migration & Backup for WordPress</p>
					</div>

					<div class="restorewp-tabs">
						<button class="restorewp-tab ${activeTab === 'export' ? 'active' : ''}" data-tab="export">${restoreWP.strings.export}</button>
						<button class="restorewp-tab ${activeTab === 'import' ? 'active' : ''}" data-tab="import">${restoreWP.strings.import}</button>
						<button class="restorewp-tab ${activeTab === 'backups' ? 'active' : ''}" data-tab="backups">${restoreWP.strings.backups}</button>
					</div>

					<div class="restorewp-content">
						<div id="restorewp-export-content" class="restorewp-tab-content ${activeTab === 'export' ? 'active' : ''}">
							${this.getExportContent()}
						</div>
						<div id="restorewp-import-content" class="restorewp-tab-content ${activeTab === 'import' ? 'active' : ''}">
							${this.getImportContent()}
						</div>
						<div id="restorewp-backups-content" class="restorewp-tab-content ${activeTab === 'backups' ? 'active' : ''}">
							${this.getBackupsContent()}
						</div>
					</div>
				</div>
			`;

			container.html(html);
			this.bindTabEvents();
			this.loadBackups();
		},

		bindTabEvents: function() {
			$(document).on('click', '.restorewp-tab', function() {
				const tab = $(this).data('tab');
				
				$('.restorewp-tab').removeClass('active');
				$(this).addClass('active');
				
				$('.restorewp-tab-content').removeClass('active');
				$('#restorewp-' + tab + '-content').addClass('active');
			});
		},

		getExportContent: function() {
			return `
				<div class="restorewp-card">
					<h3>${restoreWP.strings.exportSite}</h3>
					<div class="restorewp-export-options">
						<div class="restorewp-form-group">
							<label>
								<input type="checkbox" name="include_database" checked> 
								${__('Database', 'restorewp')}
							</label>
						</div>
						<div class="restorewp-form-group">
							<label>
								<input type="checkbox" name="include_uploads" checked> 
								${__('Media Files', 'restorewp')}
							</label>
						</div>
						<div class="restorewp-form-group">
							<label>
								<input type="checkbox" name="include_themes" checked> 
								${__('Themes', 'restorewp')}
							</label>
						</div>
						<div class="restorewp-form-group">
							<label>
								<input type="checkbox" name="include_plugins" checked> 
								${__('Plugins', 'restorewp')}
							</label>
						</div>
					</div>
					<div id="restorewp-export-status"></div>
					<button class="restorewp-button restorewp-export-btn">${restoreWP.strings.startExport}</button>
				</div>
			`;
		},

		getImportContent: function() {
			return `
				<div class="restorewp-card">
					<h3>${restoreWP.strings.importSite}</h3>
					<div class="restorewp-upload-area">
						<div class="restorewp-upload-icon">
							<span class="dashicons dashicons-upload"></span>
						</div>
						<div class="restorewp-upload-text">${restoreWP.strings.selectFile}</div>
						<div class="restorewp-upload-hint">${__('Maximum file size: 2GB', 'restorewp')}</div>
						<input type="file" id="restorewp-file-input" accept=".zip" style="display: none;">
						<button class="restorewp-button" onclick="document.getElementById('restorewp-file-input').click()">${restoreWP.strings.selectFile}</button>
					</div>
					<div class="restorewp-import-options" style="margin-top: 20px;">
						<div class="restorewp-form-group">
							<label>
								<input type="checkbox" name="create_backup" checked> 
								${__('Create backup before import', 'restorewp')}
							</label>
						</div>
					</div>
					<div id="restorewp-import-status"></div>
					<button class="restorewp-button restorewp-import-btn" disabled>${restoreWP.strings.startImport}</button>
				</div>
			`;
		},

		getBackupsContent: function() {
			return `
				<div class="restorewp-card">
					<div class="restorewp-flex restorewp-items-center restorewp-justify-between">
						<h3>${restoreWP.strings.manageBackups}</h3>
						<button class="restorewp-button restorewp-create-backup-btn">${__('Create Backup', 'restorewp')}</button>
					</div>
					<div id="restorewp-backups-list">
						<div class="restorewp-text-center" style="padding: 40px;">
							<div class="restorewp-loading"></div>
							<p>${__('Loading backups...', 'restorewp')}</p>
						</div>
					</div>
				</div>
			`;
		},

		handleExport: function(e) {
			e.preventDefault();
			
			const button = $(this);
			const statusDiv = $('#restorewp-export-status');
			
			button.prop('disabled', true).text(restoreWP.strings.processing);
			
			// Show progress popup
			RestoreWPAdmin.showProgressPopup('Initializing export...');
			
			const options = {
				include_database: $('input[name="include_database"]').is(':checked'),
				include_uploads: $('input[name="include_uploads"]').is(':checked'),
				include_themes: $('input[name="include_themes"]').is(':checked'),
				include_plugins: $('input[name="include_plugins"]').is(':checked'),
			};

			// Simulate progress updates
			let progress = 0;
			const progressInterval = setInterval(() => {
				progress += Math.random() * 15;
				if (progress > 90) progress = 90;
				
				let status = 'Preparing export...';
				if (progress > 20) status = 'Exporting database...';
				if (progress > 40) status = 'Exporting files...';
				if (progress > 70) status = 'Creating archive...';
				
				RestoreWPAdmin.updateProgress(Math.round(progress), status);
			}, 500);

			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: {
					action: 'restorewp_export',
					nonce: restoreWP.nonce,
					...options
				},
				success: function(response) {
					clearInterval(progressInterval);
					if (response.success) {
						RestoreWPAdmin.updateProgress(100, 'Export completed!');
						setTimeout(() => {
							RestoreWPAdmin.showDownloadButton(response.data.download_url || '#');
							statusDiv.html('<div class="restorewp-status success">' + restoreWP.strings.completed + '</div>');
						}, 1000);
					} else {
						RestoreWPAdmin.hideProgressPopup();
						statusDiv.html('<div class="restorewp-status error">' + (response.data.message || restoreWP.strings.failed) + '</div>');
					}
				},
				error: function() {
					clearInterval(progressInterval);
					RestoreWPAdmin.hideProgressPopup();
					statusDiv.html('<div class="restorewp-status error">' + restoreWP.strings.failed + '</div>');
				},
				complete: function() {
					button.prop('disabled', false).text(restoreWP.strings.startExport);
				}
			});
		},

		showProgressPopup: function(status) {
			const popup = `
				<div id="restorewp-progress-popup" class="restorewp-popup-overlay">
					<div class="restorewp-popup">
						<div class="restorewp-progress-circle">
							<svg width="120" height="120">
								<circle cx="60" cy="60" r="50" stroke="#e1e1e1" stroke-width="8" fill="none"/>
								<circle id="restorewp-progress-bar" cx="60" cy="60" r="50" stroke="#0073aa" stroke-width="8" fill="none" 
									stroke-dasharray="314" stroke-dashoffset="314" transform="rotate(-90 60 60)"/>
							</svg>
							<div class="restorewp-progress-text">0%</div>
						</div>
						<div class="restorewp-progress-status">${status}</div>
					</div>
				</div>
			`;
			$('body').append(popup);
		},

		updateProgress: function(percentage, status) {
			const circumference = 314;
			const offset = circumference - (percentage / 100) * circumference;
			$('#restorewp-progress-bar').css('stroke-dashoffset', offset);
			$('.restorewp-progress-text').text(percentage + '%');
			$('.restorewp-progress-status').text(status);
		},

		showDownloadButton: function(downloadUrl) {
			const popup = $('#restorewp-progress-popup .restorewp-popup');
			popup.html(`
				<div class="restorewp-progress-circle">
					<svg width="120" height="120">
						<circle cx="60" cy="60" r="50" stroke="#e1e1e1" stroke-width="8" fill="none"/>
						<circle cx="60" cy="60" r="50" stroke="#00a32a" stroke-width="8" fill="none" 
							stroke-dasharray="314" stroke-dashoffset="0" transform="rotate(-90 60 60)"/>
					</svg>
					<div class="restorewp-progress-text">✓</div>
				</div>
				<div class="restorewp-progress-status">Site backup created successfully!</div>
				<div style="margin-top: 20px;">
					<a href="${downloadUrl}" class="restorewp-download-btn" download>
						<span class="dashicons dashicons-download"></span>
						Download Site Backup
					</a>
				</div>
				<div style="margin-top: 15px;">
					<button class="restorewp-close-btn" onclick="RestoreWPAdmin.hideProgressPopup()">Close</button>
				</div>
			`);
		},

		showImportSuccess: function() {
			const popup = $('#restorewp-progress-popup .restorewp-popup');
			popup.html(`
				<div class="restorewp-progress-circle">
					<svg width="120" height="120">
						<circle cx="60" cy="60" r="50" stroke="#e1e1e1" stroke-width="8" fill="none"/>
						<circle cx="60" cy="60" r="50" stroke="#00a32a" stroke-width="8" fill="none" 
							stroke-dasharray="314" stroke-dashoffset="0" transform="rotate(-90 60 60)"/>
					</svg>
					<div class="restorewp-progress-text">✓</div>
				</div>
				<div class="restorewp-progress-status">Site imported successfully!</div>
				<div style="margin-top: 20px; text-align: center;">
					<p style="color: #666; margin-bottom: 15px;">Your WordPress site has been restored from the backup.</p>
				</div>
				<div style="margin-top: 15px;">
					<button class="restorewp-close-btn" onclick="RestoreWPAdmin.hideProgressPopup()">Close</button>
				</div>
			`);
		},

		hideProgressPopup: function() {
			$('#restorewp-progress-popup').fadeOut(300, function() {
				$(this).remove();
			});
		},

		handleFileSelect: function() {
			const file = this.files[0];
			const button = $('.restorewp-import-btn');
			
			if (file) {
				$('.restorewp-upload-text').text(file.name);
				button.prop('disabled', false);
			} else {
				$('.restorewp-upload-text').text(restoreWP.strings.selectFile);
				button.prop('disabled', true);
			}
		},

		handleImport: function(e) {
			e.preventDefault();
			
			const button = $(this);
			const statusDiv = $('#restorewp-import-status');
			const fileInput = $('#restorewp-file-input')[0];
			
			if (!fileInput.files[0]) {
				alert(__('Please select a file first.', 'restorewp'));
				return;
			}

			button.prop('disabled', true).text(restoreWP.strings.processing);
			
			const formData = new FormData();
			formData.append('action', 'restorewp_upload');
			formData.append('nonce', restoreWP.nonce);
			formData.append('file', fileInput.files[0]);

			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						// Start import process
						RestoreWPAdmin.startImport(response.data.filename, statusDiv, button);
					} else {
						statusDiv.html('<div class="restorewp-status error">' + (response.data.message || restoreWP.strings.failed) + '</div>');
						button.prop('disabled', false).text(restoreWP.strings.startImport);
					}
				},
				error: function() {
					statusDiv.html('<div class="restorewp-status error">' + restoreWP.strings.failed + '</div>');
					button.prop('disabled', false).text(restoreWP.strings.startImport);
				}
			});
		},

		startImport: function(filename, statusDiv, button) {
			const options = {
				filename: filename,
				create_backup: $('input[name="create_backup"]').is(':checked'),
			};

			// Show progress popup for import
			RestoreWPAdmin.showProgressPopup('Initializing import...');
			
			// Simulate progress updates during import
			let progress = 0;
			const progressInterval = setInterval(() => {
				progress += Math.random() * 10;
				if (progress > 90) progress = 90;
				
				let status = 'Preparing import...';
				if (progress > 15) status = 'Extracting backup file...';
				if (progress > 30) status = 'Validating backup...';
				if (progress > 45) status = 'Creating rollback backup...';
				if (progress > 55) status = 'Updating URLs for current domain...';
				if (progress > 70) status = 'Importing database...';
				if (progress > 85) status = 'Importing files...';
				
				RestoreWPAdmin.updateProgress(Math.round(progress), status);
			}, 800);

			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: {
					action: 'restorewp_import',
					nonce: restoreWP.nonce,
					...options
				},
				success: function(response) {
					clearInterval(progressInterval);
					if (response.success) {
						RestoreWPAdmin.updateProgress(100, 'Import completed successfully!');
						setTimeout(() => {
							RestoreWPAdmin.showImportSuccess();
							statusDiv.html('<div class="restorewp-status success">' + restoreWP.strings.completed + '</div>');
							RestoreWPAdmin.loadBackups(); // Refresh backups list
						}, 1000);
					} else {
						RestoreWPAdmin.hideProgressPopup();
						statusDiv.html('<div class="restorewp-status error">' + (response.data.message || restoreWP.strings.failed) + '</div>');
					}
				},
				error: function() {
					clearInterval(progressInterval);
					RestoreWPAdmin.hideProgressPopup();
					statusDiv.html('<div class="restorewp-status error">' + restoreWP.strings.failed + '</div>');
				},
				complete: function() {
					button.prop('disabled', false).text(restoreWP.strings.startImport);
				}
			});
		},

		loadBackups: function() {
			const container = $('#restorewp-backups-list');
			
			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: {
					action: 'restorewp_backup_list',
					nonce: restoreWP.nonce
				},
				success: function(response) {
					if (response.success && response.data.length > 0) {
						let html = '<ul class="restorewp-backup-list">';
						response.data.forEach(function(backup) {
							html += `
								<li class="restorewp-backup-item">
									<div class="restorewp-backup-info">
										<div class="restorewp-backup-name">${backup.filename}</div>
										<div class="restorewp-backup-meta">${backup.size_human} • ${backup.created_human}</div>
									</div>
									<div class="restorewp-backup-actions">
										<a href="${backup.download_url}" class="restorewp-button secondary">${restoreWP.strings.download}</a>
										<button class="restorewp-button danger restorewp-backup-delete" data-filename="${backup.filename}">${restoreWP.strings.delete}</button>
									</div>
								</li>
							`;
						});
						html += '</ul>';
						container.html(html);
					} else {
						container.html(`
							<div class="restorewp-text-center" style="padding: 40px;">
								<span class="dashicons dashicons-database" style="font-size: 48px; color: #a7aaad;"></span>
								<p>${__('No backups found. Create your first backup!', 'restorewp')}</p>
							</div>
						`);
					}
				},
				error: function() {
					container.html(`
						<div class="restorewp-status error">
							${__('Failed to load backups.', 'restorewp')}
						</div>
					`);
				}
			});
		},

		handleBackupDelete: function(e) {
			e.preventDefault();
			
			const filename = $(this).data('filename');
			
			if (!confirm(restoreWP.strings.confirmDelete)) {
				return;
			}

			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: {
					action: 'restorewp_backup_delete',
					nonce: restoreWP.nonce,
					filename: filename
				},
				success: function(response) {
					if (response.success) {
						RestoreWPAdmin.loadBackups();
					} else {
						alert(response.data.message || restoreWP.strings.failed);
					}
				},
				error: function() {
					alert(restoreWP.strings.failed);
				}
			});
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		RestoreWPAdmin.init();
	});

	// Make RestoreWPAdmin globally accessible for debugging
	window.RestoreWPAdmin = RestoreWPAdmin;

})(jQuery);