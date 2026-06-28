<?php
/*
 *		Copyright (C) 2018 Laksamadi Guko.
 *
 *		This program is free software; you can redistribute it and/or modify
 *		it under the terms of the GNU General Public License as published by
 *		the Free Software Foundation; either version 2 of the License, or
 *		(at your option) any later version.
 *
 *		This program is distributed in the hope that it will be useful,
 *		but WITHOUT ANY WARRANTY; without even the implied warranty of
 *		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.		See the
 *		GNU General Public License for more details.
 *
 *		You should have received a copy of the GNU General Public License
 *		along with this program.		If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
 // hide all error
error_reporting(0);
?>
<!DOCTYPE html>
<html>
	<head>
		<title>MIKHMON <?= $hotspotname; ?></title>
		<meta charset="utf-8">
		<meta http-equiv="cache-control" content="private" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<!-- Tell the browser to be responsive to screen width -->
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow, noarchive" />
		<!-- Theme color -->
		<meta name="theme-color" content="<?= $themecolor ?>" />
		<!-- Font Awesome -->
		<link rel="stylesheet" type="text/css" href="css/font-awesome/css/font-awesome.min.css" />
		<!-- Mikhmon UI -->
		<link rel="stylesheet" href="css/mikhmon-ui.<?= $theme; ?>.min.css">
		<!-- favicon -->
		<link rel="icon" href="./img/favicon.png" />
		<!-- jQuery -->
		<script src="js/jquery.min.js"></script>
		<!-- pace -->
		<link href="css/pace.<?= $theme; ?>.css" rel="stylesheet" />
		<script src="js/pace.min.js"></script>

		<!-- Modern CSS Overrides (Dynamic Dark/Light Mode) -->
		<link rel="stylesheet" href="css/modern-override.css?t=<?= time() ?>">
		<?php
		if (!class_exists('\App\Models\AppSettings')) {
			include_once(__DIR__ . '/autoload.php');
		}
		$dbSettings = new \App\Models\AppSettings();
		$portal_accent_color = $dbSettings->get('portal_accent_color', '#3c50e0');
		
		// Convert hex to rgb for rgba fallbacks
		$hex = str_replace('#', '', $portal_accent_color);
		if (strlen($hex) == 3) {
			$r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
			$g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
			$b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
		} else {
			$r = hexdec(substr($hex, 0, 2));
			$g = hexdec(substr($hex, 2, 2));
			$b = hexdec(substr($hex, 4, 2));
		}
		$primary_glow = "rgba($r, $g, $b, 0.12)";
		$primary_glow_light = "rgba($r, $g, $b, 0.08)";
		$border_hover = "rgba($r, $g, $b, 0.4)";
		$border_hover_light = "rgba($r, $g, $b, 0.2)";
		// Calculate darker shade for gradient (mix with black ~25%)
		$r_dark = max(0, (int)($r * 0.75));
		$g_dark = max(0, (int)($g * 0.75));
		$b_dark = max(0, (int)($b * 0.75));
		$accent_dark = sprintf('#%02x%02x%02x', $r_dark, $g_dark, $b_dark);
		$welcome_bg = "linear-gradient(135deg, {$portal_accent_color} 0%, {$accent_dark} 100%)";
		?>
		<style>
		:root {
			--primary: <?= $portal_accent_color ?> !important;
			--primary-dim: <?= $portal_accent_color ?> !important;
			--primary-glow: <?= $primary_glow ?> !important;
			--primary-hover: <?= $portal_accent_color ?> !important;
			--primary-hover-text: <?= $portal_accent_color ?> !important;
			--border-hover: <?= $border_hover ?> !important;
			--info: <?= $portal_accent_color ?> !important;
			--welcome-bg: <?= $welcome_bg ?> !important;
		}
		body.theme-dark {
			--primary: <?= $portal_accent_color ?> !important;
			--primary-dim: <?= $portal_accent_color ?> !important;
			--primary-glow: <?= $primary_glow ?> !important;
			--primary-hover: <?= $portal_accent_color ?> !important;
			--primary-hover-text: <?= $portal_accent_color ?> !important;
			--border-hover: <?= $border_hover ?> !important;
			--info: <?= $portal_accent_color ?> !important;
			--welcome-bg: <?= $welcome_bg ?> !important;
		}
		body.theme-light, body.theme-blue, body.theme-green, body.theme-pink {
			--primary: <?= $portal_accent_color ?> !important;
			--primary-dim: <?= $portal_accent_color ?> !important;
			--primary-glow: <?= $primary_glow_light ?> !important;
			--primary-hover: <?= $portal_accent_color ?> !important;
			--primary-hover-text: <?= $portal_accent_color ?> !important;
			--border-hover: <?= $border_hover_light ?> !important;
			--info: <?= $portal_accent_color ?> !important;
			--welcome-bg: <?= $welcome_bg ?> !important;
		}
		</style>
	</head>
	<body class="theme-<?= $theme; ?>">
		<div class="wrapper">

			
