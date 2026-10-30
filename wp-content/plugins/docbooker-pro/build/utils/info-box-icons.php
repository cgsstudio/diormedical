<?php
/**
 * Info Box icon SVG map (PHP mirror of src/components/Icon.js (renamed)).
 * Keep entries in sync with the JS array.
 */

if ( ! function_exists( 'wpddbp_info_box_icon_map' ) ) {
	function wpddbp_info_box_icon_map() {
		static $cache = null;
		if ( $cache !== null ) return $cache;

		$stroke = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
		$cache  = [
			'check'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><polyline points="20 6 9 17 4 12"/></svg>',
			'x'              => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
			'info'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
			'alert-triangle' => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
			'alert-circle'   => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
			'help-circle'    => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
			'star'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
			'heart'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
			'thumbs-up'      => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9A2 2 0 0 0 19.66 9z"/><line x1="7" y1="22" x2="7" y2="11"/></svg>',
			'award'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>',
			'shield'         => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
			'lock'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
			'key'            => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>',
			'mail'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
			'phone'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
			'map-pin'        => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
			'calendar'       => '<svg viewBox="0 0 24 24" ' . $stroke . '><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
			'clock'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
			'user'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			'users'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
			'settings'       => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
			'search'         => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
			'send'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
			'share'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
			'edit'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
			'trash'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
			'upload'         => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
			'download'       => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
			'plus'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
			'minus'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="5" y1="12" x2="19" y2="12"/></svg>',
			'arrow-right'    => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
			'arrow-left'     => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
			'arrow-up'       => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
			'arrow-down'     => '<svg viewBox="0 0 24 24" ' . $stroke . '><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
			'chevron-right'  => '<svg viewBox="0 0 24 24" ' . $stroke . '><polyline points="9 18 15 12 9 6"/></svg>',
			'dumbbell'       => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M14.4 14.4 9.6 9.6"/><path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z"/><path d="m21.5 21.5-1.4-1.4"/><path d="M3.9 3.9 2.5 2.5"/><path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z"/></svg>',
			'activity'       => '<svg viewBox="0 0 24 24" ' . $stroke . '><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
			'target'         => '<svg viewBox="0 0 24 24" ' . $stroke . '><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
			'zap'            => '<svg viewBox="0 0 24 24" ' . $stroke . '><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
			'flame'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
			'trophy'         => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
			'trending-up'    => '<svg viewBox="0 0 24 24" ' . $stroke . '><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
			'briefcase'      => '<svg viewBox="0 0 24 24" ' . $stroke . '><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
			'book'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
			'file'           => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
			'image'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
			'video'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
			'music'          => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
			'headphones'     => '<svg viewBox="0 0 24 24" ' . $stroke . '><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>',
		];
		return $cache;
	}
}

if ( ! function_exists( 'wpddbp_get_info_box_icon_svg' ) ) {
	function wpddbp_get_info_box_icon_svg( $name ) {
		$map = wpddbp_info_box_icon_map();
		return isset( $map[ $name ] ) ? $map[ $name ] : '';
	}
}
