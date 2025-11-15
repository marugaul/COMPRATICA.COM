<div class="wb-store-cart-wrp">
	<?php if ($icon && is_string($icon)): ?>
	<img src="<?php echo htmlspecialchars($icon); ?>"
		 alt="<?php echo htmlspecialchars($name); ?>"
		 title="<?php echo htmlspecialchars($name); ?>"
         <?php if ($iconWidth): ?>width="<?php echo $iconWidth ?>"<?php endif ?>
         <?php if ($iconHeight): ?>height="<?php echo $iconHeight ?>"<?php endif ?>
    />
	<?php endif; ?>
	<div>
		<?php if ($icon && is_object($icon)): ?>
            <svg width="<?= $icon->faWidth ?>" height="<?= $icon->faHeight ?>" viewBox="0 0 <?= $icon->faWidth ?> <?= $icon->faHeight ?>"
                 style="direction: ltr; margin-right: 4px; <?php if ($iconHeight): ?>height:<?php echo $iconHeight ?>; <?php endif ?><?php if ($iconWidth): ?>width:<?php echo $iconWidth ?>; <?php endif ?><?php if ($iconColor): ?>color:<?php echo $iconColor ?>; <?php endif ?>"
                 xmlns="http://www.w3.org/2000/svg">
                <text x="<?= $icon->faXOffset ?>" y="<?= $icon->faBaseLine ?>" font-size="<?= $icon->faFontSize ?>" fill="currentColor" style="font-family: FontAwesome;">&#x<?= $icon->faCharacter ?>;</text>
            </svg>
		<?php endif; ?>
		<span class="store-cart-name"><?php
			if ($name):
			?><span><?php echo $this->noPhp($name); ?></span><?php
			endif;
			?>&nbsp;<span class="store-cart-counter">(<?php echo $count; ?>)</span>
		</span>
	</div>
	<script type="text/javascript">
		$(function() { wb_require(['store/js/StoreCartElement'], function(app) { app.init('<?php echo $elementId; ?>', '<?php echo $cartUrl; ?>'); }); });
	</script>
</div>
