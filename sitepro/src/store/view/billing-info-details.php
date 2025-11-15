<?php
/* @var $this StoreElement */
?>
<h3 style="margin-bottom: 20px;" class="wb-store-sys-text"><?php echo $title; ?></h3>
<div class="row">
	<div class="col-xs-12 wb-store-sys-text">
		<label class="wb-store-sys-text"><?php echo $this->__('Email'); ?>:</label> {{<?php echo $source; ?>.email}}<br/>
		<?php if (isset($needPhone) && $needPhone): ?>
		<label class="wb-store-sys-text"><?php echo $this->__('Phone Number'); ?>:</label> {{<?php echo $source; ?>.phone}}<br/>
		<?php endif; ?>
		<div data-ng-if="!<?php echo $source; ?>.isCompany">
			<label class="wb-store-sys-text"><?php echo $this->__('First Name'); ?>:</label> {{<?php echo $source; ?>.firstName}}<br/>
			<label class="wb-store-sys-text"><?php echo $this->__('Last Name'); ?>:</label> {{<?php echo $source; ?>.lastName}}<br/>
		</div>
		<div data-ng-if="<?php echo $source; ?>.isCompany">
			<label class="wb-store-sys-text"><?php echo $this->__('Company Name'); ?>:</label> {{<?php echo $source; ?>.companyName}}<br/>
			<label class="wb-store-sys-text"><?php echo $this->__('Company Code'); ?>:</label> {{<?php echo $source; ?>.companyCode}}<br/>
			<span data-ng-if="<?php echo $source; ?>.companyVatCode"><label class="wb-store-sys-text"><?php echo $this->__('Company TAX/VAT number'); ?>:</label> {{<?php echo $source; ?>.companyVatCode}}<br/></span>
		</div>
		<label class="wb-store-sys-text"><?php echo $this->__('Address'); ?>:</label> {{<?php echo $source; ?>.address1}}<br/>

		<label class="wb-store-sys-text"><?php echo $this->__('City'); ?>:</label> {{<?php echo $source; ?>.city}}<br/>
		<label class="wb-store-sys-text"><?php echo $this->__('Post Code'); ?>:</label> {{<?php echo $source; ?>.postCode}}<br/>
		<label class="wb-store-sys-text" data-ng-hide="<?php echo $source; ?>.countryCode === 'US'"><?php echo $this->__('Region'); ?>:</label><label data-ng-show="<?php echo $source; ?>.countryCode === 'US'"><?php echo $this->__('State / Province'); ?>:</label> {{<?php echo $source; ?>.region}}<br/>
		<label class="wb-store-sys-text"><?php echo $this->__('Country'); ?>:</label> {{<?php echo $source; ?>.country}}<br/>
	</div>
</div>