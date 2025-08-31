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
		currentProcessId: null,
		statusInterval: null,

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
						<p>Migration & Backup for WordPress - Simple, Secure, Reliable</p>
					</div>

					<div class="restorewp-tabs">
						<button class="restorewp-tab ${activeTab === 'export' ? 'active' : ''}" data-tab="export">
							<span class="dashicons dashicons-download"></span>
							${restoreWP.strings.export}
						</button>
						<button class="restorewp-tab ${activeTab === 'import' ? 'active' : ''}" data-tab="import">
							<span class="dashicons dashicons-upload"></span>
							${restoreWP.strings.import}
						</button>
						<button class="restorewp-tab ${activeTab === 'backups' ? 'active' : ''}" data-tab="backups">
							<span class="dashicons dashicons-database"></span>
							${restoreWP.strings.backups}
						</button>
					</div>

					<div class="restorewp-content">
						<div id="restorewp-export-content" class="restorewp-tab-content ${activeTab === 'export' ? '' : 'restorewp-hidden'}">
							${this.getExportContent()}
						</div>
						<div id="restorewp-import-content" class="restorewp-tab-content ${activeTab === 'import' ? '' : 'restorewp-hidden'}">
							${this.getImportContent()}
						</div>
						<div id="restorewp-backups-content" class="restorewp-tab-content ${activeTab === 'backups' ? '' : 'restorewp-hidden'}">
							${this.getBackupsContent()}
						</div>
					</div>
				</div>
			`;

			container.html(html);
			this.bindTabEvents();
			if (activeTab === 'backups') {
				this.loadBackups();
			}
		},

		bindTabEvents: function() {
			$(document).on('click', '.restorewp-tab', function() {
				const tab = $(this).data('tab');
				
				// Update active tab
				$('.restorewp-tab').removeClass('active');
				$(this).addClass('active');
				
				// Hide all tab content
				$('.restorewp-tab-content').addClass('restorewp-hidden');
				
				// Show selected tab content
				$('#restorewp-' + tab + '-content').removeClass('restorewp-hidden');
				
				// Load backups if switching to backups tab
				if (tab === 'backups') {
					RestoreWPAdmin.loadBackups();
				}
			});
		},

		getExportContent: function() {
			return `
				<div class="restorewp-card">
					<div class="restorewp-flex restorewp-items-center restorewp-justify-between" style="margin-bottom: 20px;">
						<h3 style="margin: 0;">Create Site Backup</h3>
						<span class="dashicons dashicons-download" style="font-size: 24px; color: #0073aa;"></span>
					</div>
					
					<div class="restorewp-background-notice">
						<span class="dashicons dashicons-info"></span>
						<strong>Background Processing:</strong> Export runs in the background without affecting your site's frontend performance. You can cancel the process at any time.
					</div>
					
					<div class="restorewp-notice info">
						<strong>Export your complete WordPress site</strong><br>
						This will create a downloadable backup containing all your selected content.
					</div>

					<div class="restorewp-option-group">
						<h4>What to include in your backup:</h4>
						<div class="restorewp-checkbox-list">
							<div class="restorewp-checkbox-item">
								<input type="checkbox" name="include_database" id="include_database" checked>
								<label for="include_database">
									<strong>Database</strong><br>
									<small>Posts, pages, settings, users</small>
								</label>
							</div>
							<div class="restorewp-checkbox-item">
								<input type="checkbox" name="include_uploads" id="include_uploads" checked>
								<label for="include_uploads">
									<strong>Media Files</strong><br>
									<small>Images, documents, uploads</small>
								</label>
							</div>
							<div class="restorewp-checkbox-item">
								<input type="checkbox" name="include_themes" id="include_themes" checked>
								<label for="include_themes">
									<strong>Themes</strong><br>
									<small>All installed themes</small>
								</label>
							</div>
							<div class="restorewp-checkbox-item">
								<input type="checkbox" name="include_plugins" id="include_plugins" checked>
								<label for="include_plugins">
									<strong>Plugins</strong><br>
									<small>All installed plugins</small>
								</label>
							</div>
						</div>
					</div>

					<div id="restorewp-export-status"></div>
					
					<div style="text-align: center; margin-top: 30px;">
						<button class="restorewp-button restorewp-export-btn" style="padding: 15px 30px; font-size: 16px;">
							<span class="dashicons dashicons-download" style="margin-right: 8px;"></span>
							${restoreWP.strings.startExport}
						</button>
					</div>
				</div>
			`;
		},

		getImportContent: function() {
			return `
				<div class="restorewp-card">
					<div class="restorewp-flex restorewp-items-center restorewp-justify-between" style="margin-bottom: 20px;">
						<h3 style="margin: 0;">Import Site Backup</h3>
						<span class="dashicons dashicons-upload" style="font-size: 24px; color: #0073aa;"></span>
					</div>
					
					<div class="restorewp-background-notice">
						<span class="dashicons dashicons-info"></span>
						<strong>Background Processing:</strong> Import runs in the background without affecting your site's frontend performance. You can cancel the process at any time.
					</div>
					
					<div class="restorewp-notice warning">
						<strong>Important:</strong> This will replace your current site content with the backup data.<br>
						Your domain URL will be preserved automatically.
					</div>

					<div class="restorewp-upload-area">
						<div class="restorewp-upload-icon">
							<span class="dashicons dashicons-upload"></span>
						</div>
						<div class="restorewp-upload-text">${restoreWP.strings.selectFile}</div>
						<div class="restorewp-upload-hint">Maximum file size: 2GB • Supported format: ZIP</div>
						<input type="file" id="restorewp-file-input" accept=".zip" style="display: none;">
						<div style="margin-top: 20px;">
							<button class="restorewp-button secondary" onclick="document.getElementById('restorewp-file-input').click()">
								<span class="dashicons dashicons-media-default" style="margin-right: 8px;"></span>
								${restoreWP.strings.selectFile}
							</button>
						</div>
					</div>
					
					<div class="restorewp-option-group" style="margin-top: 25px;">
						<h4>Import Options:</h4>
						<div class="restorewp-checkbox-item">
							<input type="checkbox" name="create_backup" id="create_backup" checked>
							<label for="create_backup">
								<strong>Create safety backup before import</strong><br>
								<small>Recommended: Backup your current site before importing</small>
							</label>
						</div>
					</div>

					<div id="restorewp-import-status"></div>
					
					<div style="text-align: center; margin-top: 30px;">
						<button class="restorewp-button restorewp-import-btn" disabled style="padding: 15px 30px; font-size: 16px;">
							<span class="dashicons dashicons-upload" style="margin-right: 8px;"></span>
							${restoreWP.strings.startImport}
						</button>
					</div>
				</div>
			`;
		},

		getBackupsContent: function() {
			return `
				<div class="restorewp-card">
					<div class="restorewp-flex restorewp-items-center restorewp-justify-between" style="margin-bottom: 20px;">
						<div>
							<h3 style="margin: 0;">Manage Backups</h3>
							<p style="margin: 5px 0 0 0; color: #646970;">View, download, and manage your site backups</p>
						</div>
						<span class="dashicons dashicons-database" style="font-size: 24px; color: #0073aa;"></span>
					</div>
					
					<div id="restorewp-backups-list">
						<div class="restorewp-text-center" style="padding: 60px 20px;">
							<div class="restorewp-loading" style="margin: 0 auto 20px auto;"></div>
							<p style="color: #646970; font-size: 16px;">${__('Loading backups...', 'restorewp')}</p>
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
			
			// Show progress popup with cancel button
			RestoreWPAdmin.showProgressPopup('Initializing export...', true);
			
			const options = {
				include_database: $('input[name="include_database"]').is(':checked'),
				include_uploads: $('input[name="include_uploads"]').is(':checked'),
				include_themes: $('input[name="include_themes"]').is(':checked'),
				include_plugins: $('input[name="include_plugins"]').is(':checked'),
			};

			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: {
					action: 'restorewp_export',
					nonce: restoreWP.nonce,
					...options
				},
				success: function(response) {
					if (response.success) {
						RestoreWPAdmin.currentProcessId = response.data.process_id;
						RestoreWPAdmin.startStatusPolling();
					} else {
						RestoreWPAdmin.hideProgressPopup();
						statusDiv.html('<div class="restorewp-status error">' + (response.data.message || restoreWP.strings.failed) + '</div>');
						button.prop('disabled', false).text(restoreWP.strings.startExport);
					}
				},
				error: function() {
					RestoreWPAdmin.hideProgressPopup();
					statusDiv.html('<div class="restorewp-status error">' + restoreWP.strings.failed + '</div>');
					button.prop('disabled', false).text(restoreWP.strings.startExport);
				}
			});
		},

		showProgressPopup: function(status, showCancel = false) {
			const cancelButton = showCancel ? `
				<div style="margin-top: 20px;">
					<button class="restorewp-cancel-btn" onclick="RestoreWPAdmin.cancelProcess()">
						<span class="dashicons dashicons-no"></span>
						Cancel Process
					</button>
				</div>
			` : '';

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
						${cancelButton}
					</div>
				</div>
			`;
			$('body').append(popup);
		},

		startStatusPolling: function() {
			if (RestoreWPAdmin.statusInterval) {
				clearInterval(RestoreWPAdmin.statusInterval);
			}

			RestoreWPAdmin.statusInterval = setInterval(() => {
				if (!RestoreWPAdmin.currentProcessId) {
					return;
				}

				$.ajax({
					url: restoreWP.ajaxUrl,
					type: 'POST',
					data: {
						action: 'restorewp_status',
						nonce: restoreWP.nonce,
						process_id: RestoreWPAdmin.currentProcessId
					},
					success: function(response) {
						if (response.success) {
							const status = response.data;
							RestoreWPAdmin.updateProgress(status.progress || 0, status.message);

							// Check if process is complete
							if (status.status === 'completed') {
								clearInterval(RestoreWPAdmin.statusInterval);
								RestoreWPAdmin.handleProcessComplete(status);
							} else if (status.status === 'error' || status.status === 'cancelled') {
								clearInterval(RestoreWPAdmin.statusInterval);
								RestoreWPAdmin.handleProcessError(status);
							}
						}
					},
					error: function() {
						// Continue polling on error, might be temporary
					}
				});
			}, 1000);
		},

		cancelProcess: function() {
			if (!RestoreWPAdmin.currentProcessId) {
				return;
			}

			if (!confirm('Are you sure you want to cancel this process? This action cannot be undone.')) {
				return;
			}

			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: {
					action: 'restorewp_cancel',
					nonce: restoreWP.nonce,
					process_id: RestoreWPAdmin.currentProcessId
				},
				success: function(response) {
					if (response.success) {
						RestoreWPAdmin.updateProgress(0, 'Process cancelled');
						setTimeout(() => {
							RestoreWPAdmin.hideProgressPopup();
							RestoreWPAdmin.resetButtons();
						}, 1500);
					}
				}
			});
		},

		handleProcessComplete: function(status) {
			if (status.data && status.data.download_url) {
				// Export completed
				RestoreWPAdmin.showDownloadButton(status.data.download_url);
			} else {
				// Import completed
				RestoreWPAdmin.showImportSuccess();
			}
			RestoreWPAdmin.currentProcessId = null;
		},

		handleProcessError: function(status) {
			RestoreWPAdmin.hideProgressPopup();
			const statusDiv = status.type === 'export' ? $('#restorewp-export-status') : $('#restorewp-import-status');
			statusDiv.html('<div class="restorewp-status error">' + status.message + '</div>');
			RestoreWPAdmin.resetButtons();
			RestoreWPAdmin.currentProcessId = null;
		},

		resetButtons: function() {
			$('.restorewp-export-btn').prop('disabled', false).text(restoreWP.strings.startExport);
			$('.restorewp-import-btn').prop('disabled', false).text(restoreWP.strings.startImport);
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

			// Show progress popup for import with cancel button
			RestoreWPAdmin.showProgressPopup('Initializing import...', true);

			$.ajax({
				url: restoreWP.ajaxUrl,
				type: 'POST',
				data: {
					action: 'restorewp_import',
					nonce: restoreWP.nonce,
					...options
				},
				success: function(response) {
					if (response.success) {
						RestoreWPAdmin.currentProcessId = response.data.process_id;
						RestoreWPAdmin.startStatusPolling();
					} else {
						RestoreWPAdmin.hideProgressPopup();
						statusDiv.html('<div class="restorewp-status error">' + (response.data.message || restoreWP.strings.failed) + '</div>');
						button.prop('disabled', false).text(restoreWP.strings.startImport);
					}
				},
				error: function() {
					RestoreWPAdmin.hideProgressPopup();
					statusDiv.html('<div class="restorewp-status error">' + restoreWP.strings.failed + '</div>');
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
						let html = '<div class="restorewp-backup-list">';
						response.data.forEach(function(backup) {
							html += `
								<div class="restorewp-backup-item">
									<div class="restorewp-backup-icon">
										<span class="dashicons dashicons-archive"></span>
									</div>
									<div class="restorewp-backup-info">
										<div class="restorewp-backup-name">${backup.filename}</div>
										<div class="restorewp-backup-meta">
											<span class="restorewp-backup-size">${backup.size_human}</span>
											<span class="restorewp-backup-date">${backup.created_human}</span>
										</div>
									</div>
									<div class="restorewp-backup-actions">
										<a href="${backup.download_url}" class="restorewp-button secondary">
											<span class="dashicons dashicons-download"></span>
											${restoreWP.strings.download}
										</a>
										<button class="restorewp-button danger restorewp-backup-delete" data-filename="${backup.filename}">
											<span class="dashicons dashicons-trash"></span>
											${restoreWP.strings.delete}
										</button>
									</div>
								</div>
							`;
						});
						html += '</div>';
						container.html(html);
					} else {
						container.html(`
							<div class="restorewp-empty-state">
								<div class="restorewp-empty-icon">
									<span class="dashicons dashicons-database"></span>
								</div>
								<h4>No backups found</h4>
								<p>Create your first backup using the Export tab to get started.</p>
								<button class="restorewp-button" onclick="$('.restorewp-tab[data-tab=\\"export\\"]').click()">
									<span class="dashicons dashicons-download"></span>
									Create Backup
								</button>
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