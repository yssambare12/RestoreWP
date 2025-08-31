/**
 * Import Tab Component
 */

import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Card, CardBody, TextControl } from '@wordpress/components';

const ImportTab = ({ addNotice }) => {
	const [isImporting, setIsImporting] = useState(false);
	const [importProgress, setImportProgress] = useState(0);
	const [importStatus, setImportStatus] = useState('');
	const [selectedFile, setSelectedFile] = useState(null);
	const [importOptions, setImportOptions] = useState({
		create_backup: true,
		old_url: '',
		new_url: window.location.origin,
	});
	const fileInputRef = useRef(null);

	const handleFileSelect = (event) => {
		const file = event.target.files[0];
		if (file) {
			// Validate file type
			if (!file.name.toLowerCase().endsWith('.zip')) {
				addNotice(__('Please select a ZIP file.', 'restorewp'), 'error');
				return;
			}

			// Validate file size
			if (file.size > window.restoreWP.maxFileSize) {
				addNotice(__('File size exceeds the maximum allowed limit.', 'restorewp'), 'error');
				return;
			}

			setSelectedFile(file);
		}
	};

	const handleImport = async () => {
		if (!selectedFile) {
			addNotice(__('Please select a backup file first.', 'restorewp'), 'error');
			return;
		}

		setIsImporting(true);
		setImportProgress(0);
		setImportStatus(__('Uploading file...', 'restorewp'));

		try {
			// First, upload the file
			const uploadFormData = new FormData();
			uploadFormData.append('action', 'restorewp_upload');
			uploadFormData.append('nonce', window.restoreWP.nonce);
			uploadFormData.append('file', selectedFile);

			setImportProgress(25);
			setImportStatus(__('Uploading backup file...', 'restorewp'));

			const uploadResponse = await fetch(window.restoreWP.ajaxUrl, {
				method: 'POST',
				body: uploadFormData,
			});

			const uploadResult = await uploadResponse.json();

			if (!uploadResult.success) {
				throw new Error(uploadResult.data.message || __('Upload failed', 'restorewp'));
			}

			setImportProgress(50);
			setImportStatus(__('Starting import process...', 'restorewp'));

			// Then, start the import
			const importFormData = new FormData();
			importFormData.append('action', 'restorewp_import');
			importFormData.append('nonce', window.restoreWP.nonce);
			importFormData.append('filename', uploadResult.data.filename);
			importFormData.append('create_backup', importOptions.create_backup ? '1' : '0');
			
			if (importOptions.old_url && importOptions.new_url) {
				importFormData.append('old_url', importOptions.old_url);
				importFormData.append('new_url', importOptions.new_url);
			}

			const importResponse = await fetch(window.restoreWP.ajaxUrl, {
				method: 'POST',
				body: importFormData,
			});

			const importResult = await importResponse.json();

			if (importResult.success) {
				setImportProgress(100);
				setImportStatus(__('Import completed successfully!', 'restorewp'));
				addNotice(__('Site imported successfully!', 'restorewp'), 'success');
				
				// Reset form
				setSelectedFile(null);
				if (fileInputRef.current) {
					fileInputRef.current.value = '';
				}
			} else {
				throw new Error(importResult.data.message || __('Import failed', 'restorewp'));
			}
		} catch (error) {
			setImportStatus(__('Import failed', 'restorewp'));
			addNotice(error.message, 'error');
		} finally {
			setIsImporting(false);
		}
	};

	const updateOption = (key, value) => {
		setImportOptions(prev => ({
			...prev,
			[key]: value,
		}));
	};

	return (
		<div className="restorewp-import-tab">
			<Card>
				<CardBody>
					<h3>{__('Import Backup', 'restorewp')}</h3>
					
					<div className="restorewp-upload-area">
						<div className="restorewp-upload-icon">
							<span className="dashicons dashicons-upload"></span>
						</div>
						<div className="restorewp-upload-text">
							{selectedFile ? selectedFile.name : __('Select a backup file to import', 'restorewp')}
						</div>
						<div className="restorewp-upload-hint">
							{__('Maximum file size: 2GB', 'restorewp')}
						</div>
						<Button
							isPrimary
							onClick={() => fileInputRef.current?.click()}
							disabled={isImporting}
							style={{ marginTop: '15px' }}
						>
							{__('Select File', 'restorewp')}
						</Button>
						<input
							ref={fileInputRef}
							type="file"
							accept=".zip"
							onChange={handleFileSelect}
							style={{ display: 'none' }}
						/>
					</div>

					<div className="restorewp-import-options" style={{ marginTop: '20px' }}>
						<h4>{__('Import Options', 'restorewp')}</h4>
						
						<CheckboxControl
							label={__('Create backup before import', 'restorewp')}
							checked={importOptions.create_backup}
							onChange={(value) => updateOption('create_backup', value)}
							help={__('Recommended: Create a backup of current site before importing', 'restorewp')}
						/>

						<div className="restorewp-url-replace">
							<TextControl
								label={__('Old URL (optional)', 'restorewp')}
								value={importOptions.old_url}
								onChange={(value) => updateOption('old_url', value)}
								placeholder="https://old-site.com"
								help={__('URL to replace in the database', 'restorewp')}
							/>
							<TextControl
								label={__('New URL (optional)', 'restorewp')}
								value={importOptions.new_url}
								onChange={(value) => updateOption('new_url', value)}
								placeholder={window.location.origin}
								help={__('New URL for the site', 'restorewp')}
							/>
						</div>
					</div>

					{isImporting && (
						<div className="restorewp-status info">
							<div className="restorewp-flex restorewp-items-center">
								<div className="restorewp-loading"></div>
								<span style={{ marginLeft: '10px' }}>{importStatus}</span>
							</div>
							{importProgress > 0 && (
								<div className="restorewp-progress">
									<div 
										className="restorewp-progress-bar" 
										style={{ width: `${importProgress}%` }}
									></div>
								</div>
							)}
						</div>
					)}

					<Button
						isPrimary
						onClick={handleImport}
						disabled={isImporting || !selectedFile}
						className="restorewp-button"
						style={{ marginTop: '20px' }}
					>
						{isImporting ? __('Importing...', 'restorewp') : __('Start Import', 'restorewp')}
					</Button>
				</CardBody>
			</Card>
		</div>
	);
};

export default ImportTab;