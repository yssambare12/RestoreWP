/**
 * Backups Tab Component
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, Modal } from '@wordpress/components';

const BackupsTab = ({ addNotice }) => {
	const [backups, setBackups] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [isCreatingBackup, setIsCreatingBackup] = useState(false);
	const [showDeleteModal, setShowDeleteModal] = useState(false);
	const [backupToDelete, setBackupToDelete] = useState(null);

	useEffect(() => {
		loadBackups();
	}, []);

	const loadBackups = async () => {
		setIsLoading(true);
		try {
			const formData = new FormData();
			formData.append('action', 'restorewp_backup_list');
			formData.append('nonce', window.restoreWP.nonce);

			const response = await fetch(window.restoreWP.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			const result = await response.json();

			if (result.success) {
				setBackups(result.data);
			} else {
				throw new Error(result.data.message || __('Failed to load backups', 'restorewp'));
			}
		} catch (error) {
			addNotice(error.message, 'error');
		} finally {
			setIsLoading(false);
		}
	};

	const createBackup = async () => {
		setIsCreatingBackup(true);
		try {
			const formData = new FormData();
			formData.append('action', 'restorewp_backup_create');
			formData.append('nonce', window.restoreWP.nonce);
			formData.append('include_database', '1');
			formData.append('include_uploads', '1');
			formData.append('include_themes', '1');
			formData.append('include_plugins', '1');

			const response = await fetch(window.restoreWP.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			const result = await response.json();

			if (result.success) {
				addNotice(__('Backup created successfully!', 'restorewp'), 'success');
				loadBackups(); // Refresh the list
			} else {
				throw new Error(result.data.message || __('Failed to create backup', 'restorewp'));
			}
		} catch (error) {
			addNotice(error.message, 'error');
		} finally {
			setIsCreatingBackup(false);
		}
	};

	const deleteBackup = async (filename) => {
		try {
			const formData = new FormData();
			formData.append('action', 'restorewp_backup_delete');
			formData.append('nonce', window.restoreWP.nonce);
			formData.append('filename', filename);

			const response = await fetch(window.restoreWP.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			const result = await response.json();

			if (result.success) {
				addNotice(__('Backup deleted successfully!', 'restorewp'), 'success');
				loadBackups(); // Refresh the list
			} else {
				throw new Error(result.data.message || __('Failed to delete backup', 'restorewp'));
			}
		} catch (error) {
			addNotice(error.message, 'error');
		}
		
		setShowDeleteModal(false);
		setBackupToDelete(null);
	};

	const confirmDelete = (backup) => {
		setBackupToDelete(backup);
		setShowDeleteModal(true);
	};

	return (
		<div className="restorewp-backups-tab">
			<Card>
				<CardBody>
					<div className="restorewp-flex restorewp-items-center restorewp-justify-between">
						<h3>{__('Manage Backups', 'restorewp')}</h3>
						<Button
							isPrimary
							onClick={createBackup}
							disabled={isCreatingBackup}
							className="restorewp-button"
						>
							{isCreatingBackup ? __('Creating...', 'restorewp') : __('Create Backup', 'restorewp')}
						</Button>
					</div>

					{isLoading ? (
						<div className="restorewp-text-center" style={{ padding: '40px' }}>
							<div className="restorewp-loading" style={{ margin: '0 auto' }}></div>
							<p>{__('Loading backups...', 'restorewp')}</p>
						</div>
					) : backups.length === 0 ? (
						<div className="restorewp-text-center" style={{ padding: '40px' }}>
							<span className="dashicons dashicons-database" style={{ fontSize: '48px', color: '#a7aaad' }}></span>
							<p>{__('No backups found. Create your first backup!', 'restorewp')}</p>
						</div>
					) : (
						<ul className="restorewp-backup-list">
							{backups.map((backup) => (
								<li key={backup.filename} className="restorewp-backup-item">
									<div className="restorewp-backup-info">
										<div className="restorewp-backup-name">
											{backup.filename}
										</div>
										<div className="restorewp-backup-meta">
											{backup.size_human} • {backup.created_human}
										</div>
									</div>
									<div className="restorewp-backup-actions">
										<Button
											isSecondary
											href={backup.download_url}
											className="restorewp-button secondary"
										>
											{__('Download', 'restorewp')}
										</Button>
										<Button
											isDestructive
											onClick={() => confirmDelete(backup)}
											className="restorewp-button danger"
										>
											{__('Delete', 'restorewp')}
										</Button>
									</div>
								</li>
							))}
						</ul>
					)}
				</CardBody>
			</Card>

			{showDeleteModal && (
				<Modal
					title={__('Confirm Delete', 'restorewp')}
					onRequestClose={() => setShowDeleteModal(false)}
				>
					<p>
						{__('Are you sure you want to delete this backup?', 'restorewp')}
					</p>
					<p><strong>{backupToDelete?.filename}</strong></p>
					<p style={{ color: '#d63638' }}>
						{__('This action cannot be undone.', 'restorewp')}
					</p>
					<div className="restorewp-flex restorewp-justify-between" style={{ marginTop: '20px' }}>
						<Button
							isSecondary
							onClick={() => setShowDeleteModal(false)}
						>
							{__('Cancel', 'restorewp')}
						</Button>
						<Button
							isDestructive
							onClick={() => deleteBackup(backupToDelete.filename)}
						>
							{__('Delete Backup', 'restorewp')}
						</Button>
					</div>
				</Modal>
			)}
		</div>
	);
};

export default BackupsTab;