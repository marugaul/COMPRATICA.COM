<?php
/**
 * @var string $title
 * @var StoreModuleOrder $order
 * @var string $invoiceUrl
 * @var string $gatewayName
 * @var string $typeText
 * @var string $phraseText
 */
?>

<h1><?php echo StoreModule::__('Order_payment'); ?> #<?php echo $order->getTransactionId(); ?><?php echo $typeText ? ' ('.$typeText.')' : ''; ?></h1>
<table cellspacing="5" cellpadding="0">
	<tr>
		<td><strong><?php echo StoreModule::__('Order ID'); ?>:</strong>&nbsp;</td>
		<td><?php echo $order->getTransactionId(); ?></td>
	</tr>
	<?php if ($order->getInvoiceDocumentNumber()) { ?>
		<tr>
			<td><strong><?php echo StoreModule::__('Invoice document number'); ?>:</strong>&nbsp;</td>
			<td><a target="_blank" href="<?php echo htmlspecialchars($invoiceUrl); ?>" style="color: blue;"><?php echo $order->getInvoiceDocumentNumber(); ?></a></td>
		</tr>
	<?php } ?>
	<?php if ($order->getCompleteDateTime()) { ?>
		<tr>
			<td><strong><?php echo StoreModule::__('Time'); ?>:</strong>&nbsp;</td>
			<td><?php echo $order->getCompleteDateTime(); ?></td>
		</tr>
	<?php } ?>
	<?php if ($gatewayName || $order->getGatewayId()) { ?>
		<tr>
			<td><strong><?php echo StoreModule::__('Payment gateway'); ?>:</strong>&nbsp;</td>
			<td><?php echo ($gatewayName ? $gatewayName : $order->getGatewayId()); ?></td>
		</tr>
	<?php } ?>

	<tr><td>&nbsp;</td></tr>

	<?php if ($order->getBuyer() && $order->getBuyer()->getData()) { ?>
		<?php echo StorePaymentApi::buildInfoHtmlTableRows(StoreModule::__('Payer (from gateway)'), $order->getBuyer()->getData()); ?>
	<?php } ?>
	<?php if ($order->getBillingInfo()) { ?>
		<?php echo StorePaymentApi::buildInfoHtmlTableRows(StoreModule::__('Billing Information'), $order->getBillingInfo()->jsonSerialize()); ?>
	<?php } ?>
	<?php if ($order->getDeliveryInfo()) { ?>
		<?php echo StorePaymentApi::buildInfoHtmlTableRows(StoreModule::__('Delivery Information'), $order->getDeliveryInfo()->jsonSerialize()); ?>
	<?php } ?>
	<?php if ($order->getOrderComment()) { ?>
		<?php echo StorePaymentApi::buildInfoHtmlTableRows(StoreModule::__('Order Comments'), nl2br(htmlspecialchars($order->getOrderComment()))); ?>
	<?php } ?>
	<?php if ($order->getShippingDescription()) { ?>
		<tr><td colspan="2"><h3><?php echo StoreModule::__('Shipping Method'); ?>:</h3></td></tr>
		<tr><td colspan="2"><?php echo $order->getShippingDescription(); ?></td></tr>
		<tr><td>&nbsp;</td></tr>
	<?php } ?>
	<?php if ($order->getItems()) { ?>
		<tr><td colspan="2"><h3><?php echo StoreModule::__('Purchase details'); ?>:</h3></td></tr>
		<?php foreach ($order->getItems() as $item) { ?>
			<tr><td colspan="2"><?php echo htmlspecialchars((string)$item); ?></td></tr>
		<?php } ?>
		<tr><td>&nbsp;</td></tr>
	<?php } ?>
	<?php if ($order->getPrice() && ($order->getShippingAmount() || $order->getTaxAmount())) { ?>
		<tr>
			<td><strong><?php echo StoreModule::__('Subtotal'); ?>:</strong></td>
			<td><strong><?php echo StorePaymentApi::getFormattedPrice($order->getPrice() - $order->getTaxAmount() - $order->getShippingAmount()); ?></strong></td>
		</tr>
	<?php } ?>
	<?php if ($order->getTaxAmount()) { ?>
		<tr>
			<td><strong><?php echo StoreModule::__('Tax amount')?>:</strong></td>
			<td><strong><?php echo StorePaymentApi::getFormattedPrice($order->getTaxAmount()); ?></strong></td>
		</tr>
	<?php } ?>
	<?php if ($order->getShippingAmount()) { ?>
		<tr>
			<td><strong><?php echo StoreModule::__('Shipping amount'); ?>:</strong></td>
			<td><strong><?php echo StorePaymentApi::getFormattedPrice($order->getShippingAmount()); ?></strong></td>
		</tr>
	<?php } ?>
	<?php if ($order->getPrice()) { ?>
		<tr>
			<td><strong><?php echo StoreModule::__('Total')?>:</strong></td>
			<td><strong><?php echo StorePaymentApi::getFormattedPrice($order->getPrice()); ?></strong></td>
		</tr>
	<?php } ?>
</table>
