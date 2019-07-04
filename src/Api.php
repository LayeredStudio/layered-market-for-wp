<?php
namespace Layered\LayeredMarketForWp;

use WP_Error;
use Puc_v4_Factory;

class Api {

	private $path;
	private $slug;
	private $type;

	public function __construct($path, $slug) {
		$this->path = $path;
		$this->slug = $slug;
		$this->type = strpos($path, '/plugins/') !== false ? 'plugin' : 'theme';

		// Auto-update
		$detailsUrl = add_query_arg([
			'licenseKey'	=>	$this->getLicenseKey()
		], $this->getMarketUrl('update'));
		$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker($detailsUrl, $path, $slug);

		add_action('admin_footer', [$this, 'adminFooter']);
	}

	public function getMarketUrl($path = '') {
		return 'https://layered.market/' . $this->type . 's/' . $this->slug . '/' . $path;
	}

	public function getLicenseKey() {
		$name = str_replace('-', '_', strtoupper($this->slug . '_' . $this->type . '_license_key'))

		return defined($name) ? constant($name) : null;
	}

	public function getLicense() {
		$licenseKey = $this->getLicenseKey();

		if (!$licenseKey) {
			return new WP_Error('license_key_not_found', 'License Key not found');
		}

		$url = add_query_arg([
			'site'			=>	urlencode(site_url())
		], 'https://layered.market/licenses/' . $licenseKey . '/verify/' . $this->slug);


		if (isset($_REQUEST[$this->slug . '-recheck-license']) || false === ($licenseData = get_transient($this->slug . '-license-data'))) {
			$response = wp_remote_get($url);

			if (in_array(wp_remote_retrieve_response_code($response), [200, 400])) {
				$licenseData = json_decode(wp_remote_retrieve_body($response), true);
				set_transient($this->slug . '-license-data', $licenseData, 24 * HOUR_IN_SECONDS);
			} else {
				$licenseData = [
					'error_code'	=>	'license_invalid',
					'error_message'	=>	'Could not verify the License Key'
				];
				set_transient($this->slug . '-license-data', $licenseData, HOUR_IN_SECONDS);
			}
		}

		if (isset($licenseData['error_code'])) {
			return new WP_Error($licenseData['error_code'], $licenseData['error_message']);
		}

		return $licenseData;
	}

	public function isLicenseActive() {
		$license = $this->getLicense();

		return !is_wp_error($license) && $license['status'] === 'active';
	}

	public function licenseDetails() {
		$license = $this->getLicense();

		echo '<div class="layered-market-license-details">';
		echo '<h3>License Details</h3>';

		if (is_wp_error($license)) {
			?>

			<div class="license-warning">
				<p><?php echo $license->get_error_message() ?></p>
			</div>

			<form method="post">
				<p><input type="submit" class="button button-primary" name="<?php echo $this->slug ?>-recheck-license" value="↻ Recheck License Key" /></p>
			</form>

			<?php
		} else {
			?>

			<p>
				<strong>Status:</strong> <span class="text-capitalize"><?php echo $license['status'] ?></span>
				(until <?php echo date(get_option('date_format'), strtotime($license['end'])) ?>)
				<small><a href="<?php echo add_query_arg($this->slug . '-recheck-license', 1) ?>" title="Recheck License">↻</a></small>
			</p>
			<p><strong>Auto renew:</strong> <?php echo $license['autoRenew'] ? 'Yes' : 'No' ?></p>
			<p><a href="https://layered.market/licenses/<?php echo $this->getLicenseKey() ?>" target="_blank">Manage License on Layered Market ❐</a></p>

			<?php
		}

		echo '</div>';
	}

	public function adminFooter() {
		?>

		<style type="text/css">
		.text-capitalize {
			text-transform: capitalize;
		}
		.layered-market-license-details {
			border-top: 1px solid rgba(70, 13, 141, 0.3);
			margin-top: 1rem;
			padding-top: 1rem;
		}
		.layered-market-license-details .license-warning {
			color: #856404;
			background-color: #fff3cd;
			border-color: #ffeeba;
			padding: 0.5rem 0.75rem;
			border-radius: 3px;
		}
		.layered-market-license-details .license-warning p {
			margin: 0;
		}
		.layered-market-license-details .button,
		.feature-section .layered-market-license-details .button {
			margin: 0;
		}
		</style>

		<?php
	}

}
