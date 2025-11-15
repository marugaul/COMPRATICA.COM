<?php
/* @var $this StoreElement */
/* @var $item \Profis\SitePro\controller\StoreDataItem */

/* @var $images \Profis\SitePro\controller\StoreImageData[] */
/* @var $backUrl string */

/* @var $imageResolution string */
/* @var $thumbResolution string */
?>
<div class="wb-store-details">
	<div class="wb-store-controls">
		<div>
			<a class="wb-store-back btn btn-default"
			   href="<?php echo htmlspecialchars($backUrl); ?>"><span class="fa fa-chevron-left"></span>&nbsp;<?php echo $this->__('Back'); ?></a>
		</div>
	</div>
	<div class="wb-store-imgs-block">
		<a class="wb-store-image"
		   href="<?php echo htmlspecialchars(($v = $images[0]->link ? tr_($images[0]->link->url) : null) ? $v : "javascript:void(0)"); ?>"
		   target="<?php echo htmlspecialchars(($v = $images[0]->link ? tr_($images[0]->link->target) : null) ? $v : "_self"); ?>">
			<?php if (empty($images) || !isset($images[0]->image->$imageResolution)): ?>
			<span class="wb-store-nothumb glyphicon glyphicon-picture"></span>
			<?php else: ?>
			<img src="<?php echo htmlspecialchars($images[0]->image->$imageResolution); ?>"
				 data-zoom-href="<?php echo htmlspecialchars($images[0]->zoom); ?>"
				 data-link="<?php echo htmlspecialchars(($v = $images[0]->link ? tr_($images[0]->link->url) : null) ? $v : ""); ?>"
				 data-target="<?php echo htmlspecialchars(($v = $images[0]->link ? tr_($images[0]->link->target) : null) ? $v : ""); ?>"
				 alt="<?php echo htmlspecialchars(($v = tr_($images[0]->title)) ? $v : tr_($item->name)); ?>"
				 title="<?php echo htmlspecialchars(($v = tr_($images[0]->title)) ? $v : tr_($item->name)); ?>" />
			<?php endif; ?>
		</a>
		<?php if (count($images) > 1): ?>
		<br/>
		<div class="wb-store-alt-images">
			<?php if (count($images) > 2): ?>
			<span class="arrow-left fa fa-chevron-left"></span>
			<span class="arrow-right fa fa-chevron-right"></span>
			<?php endif; ?>
			<div>
				<div>
                    <?php foreach ($images as $image): ?>
                        <?php if (isset($image->image->$imageResolution)): ?>
                        <div class="wb-store-alt-img">
                            <img src="<?php echo htmlspecialchars($image->image->$imageResolution); ?>"
                                 data-zoom-href="<?php echo htmlspecialchars($image->zoom); ?>"
                                 data-link="<?php echo htmlspecialchars(($v = $image->link ? tr_($image->link->url) : null) ? $v : ""); ?>"
                                 data-target="<?php echo htmlspecialchars(($v = $image->link ? tr_($image->link->target) : null) ? $v : ""); ?>"
                                 alt="<?php echo htmlspecialchars(($v = tr_($image->title)) ? $v : tr_($item->name)); ?>"
                                 title="<?php echo htmlspecialchars(($v = tr_($image->title)) ? $v : tr_($item->name)); ?>" />
					    </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
			</div>
		</div>
		<?php endif; ?>
		<?php if (isset($showDates) && $showDates && isset($item->dateTimeCreated) && $item->dateTimeCreated): ?>
		<div style="color: #c8c8c8; font-weight: normal; font-size: 14px;">
			<?php
				echo $this->__('Created').': '.date('Y-m-d', strtotime($item->dateTimeCreated))
					.((isset($item->dateTimeModified) && $item->dateTimeModified)
						? (' / '.$this->__('Modified').': '.date('Y-m-d', strtotime($item->dateTimeModified)))
						: ''
					);
			?>
		</div>
		<?php endif; ?>
	</div>
	<div class="wb-store-properties"
		<?php // if (isset($imageBlockWidth) && $imageBlockWidth > 0) echo ' style="margin-left: '.$imageBlockWidth.'px;"'; ?>>
		<div class="wb-store-name">
			<?php echo $this->noPhp(tr_($item->name)); ?>
			<?php if (isset($showItemId) && $showItemId): ?>
			&nbsp;
			<span style="color: #c8c8c8; font-weight: normal; font-size: 14px;">(ID: <?php echo $this->noPhp($item->id); ?>)</span>
			<?php endif; ?>
		</div>
		
		<table class="wb-store-details-table" style="width: 100%;">
			<tbody>
				<?php if ($cats): ?>
				<tr>
					<td class="wb-store-details-table-field-label">
						<div class="wb-store-pcats"><div class="wb-store-label"><?php echo $this->__('Category'); ?>:</div></div>
					</td>
					<td><div class="wb-store-pcats"><?php echo $this->noPhp($cats); ?></div></td>
				</tr>
				<?php endif; ?>
				
				<?php if ($item->sku): ?>
				<tr>
					<td class="wb-store-details-table-field-label">
						<div class="wb-store-sku"><div class="wb-store-label"><?php echo $this->__('SKU'); ?>:</div></div>
					</td>
					<td><div class="wb-store-sku"><?php echo $this->noPhp($item->sku); ?></div></td>
				</tr>
				<?php endif; ?>

				<?php if ($item->price && ($priceStr = $this->formatPrice($item->price))): ?>
				<tr>
					<td class="wb-store-details-table-field-label">
						<div class="wb-store-price"><div class="wb-store-label"><?php echo $this->__('Price'); ?>:</div></div>
					</td>
					<td><div class="wb-store-price"><?php echo $priceStr; ?></div></td>
				</tr>
				<?php endif; ?>

				<?php foreach ($custFields as $field): ?>
				<tr>
					<td class="wb-store-details-table-field-label">
						<div class="wb-store-field"><div class="wb-store-label"><?php echo $this->noPhp($field->name); ?>:</div></div>
					</td>
					<td><div class="wb-store-field"><?php echo $this->noPhp($field->value); ?></div></td>
				</tr>
				<?php endforeach; ?>
				
			</tbody>
		</table>

		<?php if ($hasCart){ ?>
		<div class="wb-store-form-buttons form-inline">
			<div class="form-group">
				<input class="wb-store-cart-add-quantity form-control" type="number" min="1" step="1" value="1" />
			</div>
			<button type="button" class="wb-store-cart-add-btn btn btn-success"><?php echo $this->__('Add to cart'); ?></button>
		</div>
		<?php } ?>

		<?php if (tr_($item->description)): ?>
		<div class="wb-store-desc" style="max-width: 768px;">
			<div class="wb-store-field" style="margin-bottom: 10px;"><div class="wb-store-label"><?php  echo $this->__('Description') ?></div></div>
			<?php $description = $this->noPhp(tr_($item->description)); ?>
			<div<?php if (!preg_match("#<(p|u|i|a|b|em|hr|br|ul|li|tr|td|th|h[1-6]|div|span|table|strong)\\b.*>#isuU", $description)) echo ' style="white-space: pre-line;"'; ?>><?php echo $description; ?></div>
		</div>
		<?php endif; ?>
		
		<?php if (!$hasCart && $hasForm): ?>
			<?php if ($hasFormFile) require $hasFormFile; ?>
		<?php endif; ?>
	</div>
</div>
<script type="text/javascript">
	$(function() {
		var isRTL = $('html').attr('dir') === 'rtl';
		wb_require(['store/js/StoreDetails'], function(app) { app.init('<?php echo $elementId; ?>', '<?php echo $item->id; ?>', '<?php echo $cartUrl; ?>', <?php echo json_encode($jsImages); ?>, {'Added!': <?php echo json_encode($this->__('Added!')); ?>}); });
		$(window).on('resize', function() {
			$('#<?php echo $elementId; ?> .wb-store-properties').css(isRTL ? 'margin-right' : 'margin-left', ($('#<?php echo $elementId; ?> .wb-store-imgs-block').outerWidth(true) + 5) + 'px');
		});
		$(window).trigger('resize');
	});
</script>
