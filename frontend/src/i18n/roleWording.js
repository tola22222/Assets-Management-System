// Role-specific wording, applied on top of en.json / km.json by
// applyRoleWording() in ./index.js. Every "register / registered" phrase reads:
//   - "assigned" for Staff, who see what was assigned to their location;
//   - "add asset / added" for HR (OPM) and the Accountant (Finance), who add
//     the assets (the "Add Asset" page).
// Any other role (the Executive Director) keeps the base text.

// HR and the Accountant share one set.
const addAsset = {
  en: {
    dashboard: {
      total_assets: 'Total assets added',
      registered_over_time: 'Assets added over time',
      registered_over_time_subtitle: 'New assets added, by period',
      no_trend_data: 'No assets added in this period yet.',
      no_assets: 'No assets added yet.',
      trend_failed: 'Could not load the added-assets trend.',
      trend_tooltip: '{n} asset added | {n} assets added',
    },
    import: {
      title: 'Import to Add Asset',
      qr_wait_hint: 'Large imports can take a minute.',
      view_register: 'Go to Add Asset',
    },
    locations: {
      code_missing_hint: 'Assets cannot be added or imported at this site until it has a site code.',
    },
    reports: {
      subtitle: 'Live roll-ups across added assets',
      email_body_hint: 'Sends a summary of added assets (total assets, disposals, pending workflows, etc.) to the address below.',
      col_registered: 'Added Date',
    },
  },
  km: {
    dashboard: {
      total_assets: 'ចំនួនទ្រព្យសម្បត្តិដែលបានបន្ថែមសរុប',
      registered_over_time: 'ទ្រព្យសម្បត្តិដែលបានបន្ថែមតាមពេលវេលា',
      registered_over_time_subtitle: 'ទ្រព្យសម្បត្តិថ្មីដែលបានបន្ថែម តាមរយៈពេល',
      no_trend_data: 'មិនទាន់មានទ្រព្យសម្បត្តិបានបន្ថែមក្នុងរយៈពេលនេះទេ។',
      no_assets: 'មិនទាន់មានទ្រព្យសម្បត្តិបានបន្ថែមនៅឡើយទេ។',
      trend_failed: 'មិនអាចផ្ទុកនិន្នាការបន្ថែមទ្រព្យសម្បត្តិបានទេ។',
      trend_tooltip: 'ទ្រព្យសម្បត្តិ {n} ត្រូវបានបន្ថែម',
    },
    import: {
      title: 'នាំចូលដើម្បីបន្ថែមទ្រព្យសម្បត្តិ',
      qr_wait_hint: 'ការនាំចូលធំអាចចំណាយពេលមួយនាទី។',
      view_register: 'ទៅកាន់បន្ថែមទ្រព្យសម្បត្តិ',
    },
    locations: {
      code_missing_hint: 'មិនអាចបន្ថែម ឬនាំចូលទ្រព្យសម្បត្តិនៅទីតាំងនេះបានទេ រហូតទាល់តែមានលេខកូដទីតាំង។',
    },
    reports: {
      subtitle: 'សេចក្តីសង្ខេបផ្ទាល់នៃទ្រព្យសម្បត្តិដែលបានបន្ថែម',
      email_body_hint: 'ផ្ញើសេចក្តីសង្ខេបនៃទ្រព្យសម្បត្តិដែលបានបន្ថែម (ចំនួនទ្រព្យសម្បត្តិសរុប ការបោះបង់ លំហូរការងារកំពុងរង់ចាំ។ល។) ទៅកាន់អាសយដ្ឋានខាងក្រោម។',
      col_registered: 'កាលបរិច្ឆេទបន្ថែម',
    },
  },
}

const staff = {
  en: {
    dashboard: {
      total_assets: 'Total assets assigned',
      registered_over_time: 'Assets assigned over time',
      registered_over_time_subtitle: 'New assets assigned, by period',
      no_trend_data: 'No assets assigned in this period yet.',
      no_assets: 'No assets assigned yet.',
      trend_failed: 'Could not load the assigned-assets trend.',
      trend_tooltip: '{n} asset assigned | {n} assets assigned',
    },
    import: {
      title: 'Import Assigned Assets',
      qr_wait_hint: 'Large assigned lists can take a minute.',
      view_register: 'View Assigned Assets',
    },
    locations: {
      code_missing_hint: 'Assets cannot be assigned or imported at this site until it has a site code.',
    },
    reports: {
      subtitle: 'Live roll-ups across assigned assets',
      email_body_hint: 'Sends a summary of assigned assets (total assets, disposals, pending workflows, etc.) to the address below.',
      col_registered: 'Assigned Date',
    },
  },
  km: {
    dashboard: {
      total_assets: 'ចំនួនទ្រព្យសម្បត្តិដែលបានចាត់ចែងសរុប',
      registered_over_time: 'ទ្រព្យសម្បត្តិដែលបានចាត់ចែងតាមពេលវេលា',
      registered_over_time_subtitle: 'ទ្រព្យសម្បត្តិថ្មីដែលបានចាត់ចែង តាមរយៈពេល',
      no_trend_data: 'មិនទាន់មានទ្រព្យសម្បត្តិបានចាត់ចែងក្នុងរយៈពេលនេះទេ។',
      no_assets: 'មិនទាន់មានទ្រព្យសម្បត្តិបានចាត់ចែងនៅឡើយទេ។',
      trend_failed: 'មិនអាចផ្ទុកនិន្នាការចាត់ចែងបានទេ។',
      trend_tooltip: 'ទ្រព្យសម្បត្តិ {n} ត្រូវបានចាត់ចែង',
    },
    import: {
      title: 'នាំចូលទ្រព្យសម្បត្តិដែលបានចាត់ចែង',
      view_register: 'មើលទ្រព្យសម្បត្តិដែលបានចាត់ចែង',
    },
    locations: {
      code_missing_hint: 'មិនអាចចាត់ចែង ឬនាំចូលទ្រព្យសម្បត្តិនៅទីតាំងនេះបានទេ រហូតទាល់តែមានលេខកូដទីតាំង។',
    },
    reports: {
      subtitle: 'សេចក្តីសង្ខេបផ្ទាល់នៃទ្រព្យសម្បត្តិដែលបានចាត់ចែង',
      email_body_hint: 'ផ្ញើសេចក្តីសង្ខេបនៃទ្រព្យសម្បត្តិដែលបានចាត់ចែង (ចំនួនទ្រព្យសម្បត្តិសរុប ការបោះបង់ លំហូរការងារកំពុងរង់ចាំ។ល។) ទៅកាន់អាសយដ្ឋានខាងក្រោម។',
      col_registered: 'កាលបរិច្ឆេទចាត់ចែង',
    },
  },
}

// role (users.role) => wording
export default {
  staff,
  operations_hr_manager: addAsset,
  finance_manager: addAsset,
}
