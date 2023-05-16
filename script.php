<?php

function checkInstall()
{
	if (exec('podman --version') === false) {
		throw new Exception('podman not found');
	}

	if (exec('skopeo --version') === false) {
		throw new Exception('skopeo not found');
	}
}

function checkConfig()
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

function output(string $text)
{
	echo $text . "\n";
}

function ignoreRegistries($name)
{
	$registries = ['localhost'];

	if (defined('IGNORE_REGISTRIES') === true) {
		$registries = array_merge($registries, constant('IGNORE_REGISTRIES'));
	}

	foreach ($registries as $registry) {
		if (str_starts_with($name, $registry)) {
			return true;
		}
	}

	return false;
}

function ignoreImages($name)
{
	$images = [];

	if (defined('IGNORE_IMAGES') === true) {
		$images = constant('IGNORE_IMAGES');
	}

	foreach ($images as $image) {
		if ($name === $image) {
			return true;
		}
	}

	return false;
} 

/**
 * Get Ids for all running containers
 */
function getContainerIds()
{
	exec('podman ps --format={{.ID}}', $data);
	return $data;
}

/**
 * Get name of an image
 * @param string $containerId Container ID
 */
function getImageName(string $containerId)
{
	exec('podman inspect ' . escapeshellarg($containerId) . ' --format {{.ImageName}}', $data);
	return $data[0];
} 

/**
 * Get id of an image
 * @param string $containerId Container ID
 */
function getImageId(string $containerId)
{
	exec('podman inspect ' . escapeshellarg($containerId) . ' --format {{.ImageName}}', $data);
	return $data[0];
}

/**
 * Get image creation date
 * @param string $id Image ID
 */
function getImageDate(string $id)
{
	exec('podman inspect ' . escapeshellarg($id) . ' --format {{.Created}}', $data);
	return strtotime($data[0]);
}

/**
 * Get remote image creation date
 * @param string $name Image name
 */
function getRemoteImageDate(string $name)
{
	$command = 'skopeo inspect ' . escapeshellarg('docker://'. $name) . ' --format {{.Created}}';

	exec($command, $data);
	return strtotime($data[0]);
}

function send(string $title, string $message)
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
		throw new Exception('Request failed');
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

		if (ignoreImages($imageName) === true || ignoreRegistries($imageName) === true) {
			output('Skipping ' . $imageName);
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
	output('Updates found: ' . count($imageUpdates));

	if ($imageUpdates !== []) {
		output('Sending gotify messages');

		$title = 'Podman image updates for ' . gethostname();
		$message = "Image(s) require an update: \n" . implode("\n", $imageUpdates);

		send($title, $message);
	}
} catch (Exception $e) {
	output($e->getMessage());
}
