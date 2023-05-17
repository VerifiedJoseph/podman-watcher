<?php

/**
 * Check system for podman & skopeo
 * @throws Exception if a program is not found.
 */
function checkInstall(): void
{
	if (exec('podman --version') === false) {
		throw new Exception('podman not found');
	}

	if (exec('skopeo --version') === false) {
		throw new Exception('skopeo not found');
	}
}

/**
 * Check configuration
 * @throws Exception if configuration file is not found.
 * @throws Exception if a required constant is not set or is invalid.
 */
function checkConfig(): void
{
	if (!file_exists('config.php')) {
		throw new Exception('Configuration file not found.');
	}

	require 'config.php';

	if (defined('GOTIFY_SERVER') === false || empty(constant('GOTIFY_SERVER'))) {
		throw new Exception('Gotify server must be set. [GOTIFY_SERVER]');
	}

	if (defined('GOTIFY_TOKEN') === false || empty(constant('GOTIFY_TOKEN'))) {
		throw new Exception('Gotify token must be set. [GOTIFY_TOKEN]');
	}

	if (defined('IGNORE_IMAGES') === true) {
		if (empty(constant('IGNORE_IMAGES')) || is_array(constant('IGNORE_IMAGES')) === false) {
			throw new Exception('Ignore images value is empty or not an array. [IGNORE_IMAGES]');
		}
	}

	if (defined('IGNORE_REGISTRIES') === true) {
		if (empty(constant('IGNORE_REGISTRIES')) || is_array(constant('IGNORE_REGISTRIES')) === false) {
			throw new Exception('Ignore registries value is empty or not an array. [IGNORE_REGISTRIES]');
		}
	}
}

/**
 * Output text to the terminal
 * @param string $text Text
 */
function output(string $text): void
{
	echo $text . "\n";
}

/**
 * Check if the registry of an image is on the ignore list
 * @param $name Image name
 */
function ignoreRegistry($name): bool
{
	$registries = ['localhost'];

	if (defined('IGNORE_REGISTRIES') === true) {
		$registries = array_merge($registries, constant('IGNORE_REGISTRIES'));
	}

	foreach ($registries as $registry) {
		if (str_starts_with($name, $registry)) {
			output('Skipping ' . $name . ' (Registry ignore list)');
			return true;
		}
	}

	return false;
}

/**
 * Check if an image is on the ignore list
 * @param $name Image name
 */
function ignoreImage($name): bool
{
	$images = [];

	if (defined('IGNORE_IMAGES') === true) {
		$images = constant('IGNORE_IMAGES');
	}

	foreach ($images as $image) {
		if ($name === $image) {
			output('Skipping ' . $name . ' (Image ignore list)');
			return true;
		}
	}

	return false;
} 

/**
 * Get IDs for all running containers
 */
function getContainerIds(): array
{
	exec('podman ps --format={{.ID}}', $data);
	return $data;
}

/**
 * Get image name
 * @param string $containerId Container ID
 */
function getImageName(string $containerId): string
{
	exec('podman inspect ' . escapeshellarg($containerId) . ' --format {{.ImageName}}', $data);
	return $data[0];
} 

/**
 * Get image ID
 * @param string $containerId Container ID
 */
function getImageId(string $containerId): string
{
	exec('podman inspect ' . escapeshellarg($containerId) . ' --format {{.ImageName}}', $data);
	return $data[0];
}

/**
 * Get image creation date
 * @param string $id Image ID
 */
function getImageDate(string $id): int
{
	exec('podman inspect ' . escapeshellarg($id) . ' --format {{.Created}}', $data);
	return strtotime($data[0]);
}

/**
 * Get remote image creation date
 * @param string $name Image name
 */
function getRemoteImageDate(string $name): int
{
	exec('skopeo inspect ' . escapeshellarg('docker://'. $name) . ' --format {{.Created}}', $data);
	return strtotime($data[0]);
}

/**
 * Send message using Gotify
 * @param string $title Message title
 * @param string $message Message body
 * 
 * @throws Exception if cURL request failed.
 * @throws Exception if message send failed.
 */
function sendMessage(string $title, string $message)
{
	$data = [
		'title' => $title,
		'message' => $message
	];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, constant('GOTIFY_SERVER') . '/message');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
	curl_setopt($ch, CURLOPT_ENCODING, '');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type:application/json',
		'Authorization:Bearer ' . constant('GOTIFY_TOKEN')
	]);

	curl_exec($ch);
	$info = curl_getinfo($ch);

	if (curl_errno($ch)) {
		throw new Exception('Request failed:' . curl_error($ch));
	}
	
	if ($info['http_code'] !== 200) {
		throw new Exception('Message send failed' . '(' . $info['http_code'] .')');
	}

	output('Sent message');
}

try
{
	checkInstall();
	checkConfig();

	$imageUpdates = [];
	$checkedCount = 0;
	$skippedCount = 0;

	foreach (getContainerIds() as $containerId) {
		$imageName = getImageName($containerId);

		if (ignoreImage($imageName) === true || ignoreRegistry($imageName) === true) {
			$skippedCount++;
			continue;
		}

		$imageId = getImageId($containerId);
		$imageDate = getImageDate($imageId);

		output('Checking ' . $imageName);

		$remoteImageDate = getRemoteImageDate($imageName);

		if ($remoteImageDate > $imageDate) {
			output('Found update for ' . $imageName);

			$imageUpdates[] = $imageName;
		}

		$checkedCount++;
	}

	output('Checked: ' . $checkedCount);
	output('Skipped: ' . $skippedCount);
	output('Updates: ' . count($imageUpdates));

	if ($imageUpdates !== []) {
		output('Sending gotify message');

		$title = 'Podman image updates for ' . gethostname();
		$message = "Images requiring a update: \n" . implode("\n", $imageUpdates);

		sendMessage($title, $message);
	}
} catch (Exception $e) {
	output($e->getMessage());
}
