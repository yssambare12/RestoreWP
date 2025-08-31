/**
 * Main Admin App Component
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TabPanel, Notice } from '@wordpress/components';
import ExportTab from './ExportTab';
import ImportTab from './ImportTab';
import BackupsTab from './BackupsTab';

const AdminApp = () => {
	const [notices, setNotices] = useState([]);

	const addNotice = (message, type = 'info') => {
		const notice = {
			id: Date.now(),
			message,
			type,
		};
		setNotices(prev => [...prev, notice]);

		// Auto-remove after 5 seconds
		setTimeout(() => {
			removeNotice(notice.id);
		}, 5000);
	};

	const removeNotice = (id) => {
		setNotices(prev => prev.filter(notice => notice.id !== id));
	};

	const tabs = [
		{
			name: 'export',
			title: __('Export', 'restorewp'),
			className: 'restorewp-tab-export',
		},
		{
			name: 'import',
			title: __('Import', 'restorewp'),
			className: 'restorewp-tab-import',
		},
		{
			name: 'backups',
			title: __('Backups', 'restorewp'),
			className: 'restorewp-tab-backups',
		},
	];

	return (
		<div className="restorewp-admin-container">
			<div className="restorewp-header">
				<h1>
					<span className="dashicons dashicons-migrate"></span>
					{__('RestoreWP', 'restorewp')}
				</h1>
				<p>{__('Modern WordPress migration and backup solution', 'restorewp')}</p>
			</div>

			{notices.length > 0 && (
				<div className="restorewp-notices">
					{notices.map(notice => (
						<Notice
							key={notice.id}
							status={notice.type}
							onRemove={() => removeNotice(notice.id)}
						>
							{notice.message}
						</Notice>
					))}
				</div>
			)}

			<TabPanel
				className="restorewp-tabs"
				activeClass="active"
				tabs={tabs}
			>
				{(tab) => (
					<div className="restorewp-content">
						{tab.name === 'export' && <ExportTab addNotice={addNotice} />}
						{tab.name === 'import' && <ImportTab addNotice={addNotice} />}
						{tab.name === 'backups' && <BackupsTab addNotice={addNotice} />}
					</div>
				)}
			</TabPanel>
		</div>
	);
};

export default AdminApp;