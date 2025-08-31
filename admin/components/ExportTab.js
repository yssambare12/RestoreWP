/**
 * Export Tab Component
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Card, CardBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const ExportTab = ({ addNotice }) => {
	const [isExporting, setIsExporting] = useState(false);
	const [exportProgress, setExportProgress] = useState(0);
	const [exportStatus, setExportStatus] = useState('');
	const [exportOptions, setExportOptions] = useState({
		include_database: true,
		include_uploads: true,
		include_themes: true,
		include_plugins: true,
		exclude_tables: [],
		exclude_plugins: [],
		exclude_themes: [],
	});

	const handleExport = async () => {
		setIsExporting(true);
		setExportProgress(0);
		setExportStatus(__('Starting export...', 'restorewp'));

		try {
			const formData = new FormData();
			formData.append('action', 'restorewp_export');
			formData.append('nonce', window.restoreWP.nonce);
			
			Object.keys(exportOptions).forEach(key => {
				if (Array.isArray(exportOptions[key])) {
					exportOptions[key].forEach(value => {
						formData.append(`${key}[]`, value);
					});
				} else {
					formData.append(key, exportOptions[key]);
				}
			});

			const response = await fetch(window.restoreWP.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			const result = await response.json();

			if (result.success) {
				setExportProgress(100);
				setExportStatus(__('Export completed successfully!', 'restorewp'));
				addNotice(__('Export completed successfully. Download will start automatically.', 'restorewp'), 'success');
				
				// Auto-download the file
				if (result.data.download_url) {
					window.location.href = result.data.download_url;
				}
			} else {
				throw new Error(result.data.message || __('Export failed', 'restorewp'));
			}
		} catch (error) {
			setExportStatus(__('Export failed', 'restorewp'));
			addNotice(error.message, 'error');
		} finally {
			setIsExporting(false);
		}
	};

	const updateOption = (key, value) => {
		setExportOptions(prev => ({
			...prev,
			[key]: value,
		}));
	};

	return (
		<div className="restorewp-export-tab">
			<Card>
				<CardBody>
					<h3>{__('Export Options', 'restorewp')}</h3>
					
					<div className="restorewp-export-options">
						<div className="restorewp-option-group">
							<h4>{__('What to Export', 'restorewp')}</h4>
							
							<CheckboxControl
								label={__('Database', 'restorewp')}
								checked={exportOptions.include_database}
								onChange={(value) => updateOption('include_database', value)}
								help={__('Export all database tables and content', 'restorewp')}
							/>
							
							<CheckboxControl
								label={__('Media Files (Uploads)', 'restorewp')}
								checked={exportOptions.include_uploads}
								onChange={(value) => updateOption('include_uploads', value)}
								help={__('Export all uploaded media files', 'restorewp')}
							/>
							
							<CheckboxControl
								label={__('Themes', 'restorewp')}
								checked={exportOptions.include_themes}
								onChange={(value) => updateOption('include_themes', value)}
								help={__('Export all installed themes', 'restorewp')}
							/>
							
							<CheckboxControl
								label={__('Plugins', 'restorewp')}
								checked={exportOptions.include_plugins}
								onChange={(value) => updateOption('include_plugins', value)}
								help={__('Export all installed plugins', 'restorewp')}
							/>
						</div>
					</div>

					{isExporting && (
						<div className="restorewp-status info">
							<div className="restorewp-flex restorewp-items-center">
								<div className="restorewp-loading"></div>
								<span style={{ marginLeft: '10px' }}>{exportStatus}</span>
							</div>
							{exportProgress > 0 && (
								<div className="restorewp-progress">
									<div 
										className="restorewp-progress-bar" 
										style={{ width: `${exportProgress}%` }}
									></div>
								</div>
							)}
						</div>
					)}

					<Button
						isPrimary
						onClick={handleExport}
						disabled={isExporting}
						className="restorewp-button"
					>
						{isExporting ? __('Exporting...', 'restorewp') : __('Start Export', 'restorewp')}
					</Button>
				</CardBody>
			</Card>
		</div>
	);
};

export default ExportTab;