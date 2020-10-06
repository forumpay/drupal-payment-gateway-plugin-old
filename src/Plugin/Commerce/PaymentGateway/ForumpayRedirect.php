<?php
namespace Drupal\commerce_forumpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the forumpay payment gateway
 * @CommercePaymentGateway(
 *   id = "forumpay",
 *   label = "ForumPay Payment",
 *   display_label = "Pay with Crypto (by ForumPay)",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_forumpay\PluginForm\OffsiteRedirect\ForumpayForm"
 *   }
 * )
 */

class ForumpayRedirect extends OffsitePaymentGatewayBase
{

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'pos_id' => '',
            'api_user' => '',
            'api_key' => '',
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $pos_id = !empty($this->configuration['pos_id']) ? $this->configuration['pos_id'] : '';
        $api_user = !empty($this->configuration['api_user']) ? $this->configuration['api_user'] : '';
        $api_key = !empty($this->configuration['api_key']) ? $this->configuration['api_key'] : '';

        $form['pos_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('POS ID'),
            '#default_value' => $pos_id,
            '#description' => $this->t('POS ID from ForumPay.'),
            '#required' => true,
        ];

        $form['api_user'] = [
            '#type' => 'textfield',
            '#title' => $this->t('API User'),
            '#default_value' => $api_user,
            '#description' => $this->t('API User from ForumPay.'),
            '#required' => true,
        ];

        $form['api_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('API Secret'),
            '#default_value' => $api_key,
            '#description' => $this->t('API Secret from Forumpay.'),
            '#required' => true,
        ];

        $form['mode']['#access'] = false;

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);

        if (!$form_state->getErrors() && $form_state->isSubmitted()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['pos_id'] = $values['pos_id'];
            $this->configuration['api_user'] = $values['api_user'];
            $this->configuration['api_key'] = $values['api_key'];
            $this->configuration['mode'] = 'live';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['pos_id'] = $values['pos_id'];
            $this->configuration['api_user'] = $values['api_user'];
            $this->configuration['api_key'] = $values['api_key'];
            $this->configuration['mode'] = 'live';
        }
    }
    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getPosID()
    {
        return $this->configuration['pos_id'];
    }
    /**
     * {@inheritdoc}
     */
    public function getApiUser()
    {
        return $this->configuration['api_user'];
    }

/**
 * {@inheritdoc}
 */
    public function getApikey()
    {
        return $this->configuration['api_key'];
    }
    /**
     * {@inheritdoc}
     */
    public function isTestRequest()
    {
        return $this->configuration['mode'] == 'test' ? 'TRUE' : 'FALSE';
    }

    public function update_transaction($order, $resdata)
    {

        $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
        $transactionArray = $paymentStorage->loadByProperties(['order_id' => $order->id()]);

        if (!empty($transactionArray)) {
            $transaction = array_shift($transactionArray);
        } else {

            $transaction = $paymentStorage->create([
                'payment_gateway' => $this->entityId,
                'order_id' => $order->id(),
                'remote_id' => $resdata['id'],
            ]);
        }

        $transaction->setRemoteState('complete');
        $transaction->setState('completed');
        $transaction->setAmount($order->getTotalPrice());
        $paymentStorage->save($transaction);
    }

    private function apply_order_transition($order, $orderTransition)
    {
        $order_state = $order->getState();
        $order_state_transitions = $order_state->getTransitions();
        if (!empty($order_state_transitions) && isset($order_state_transitions[$orderTransition])) {
            $order_state->applyTransition($order_state_transitions[$orderTransition]);
            $order->save();
        }
    }
    private function load_order($orderId)
    {
        $order = Order::load($orderId);
        if (!$order) {
            $this->logger->warning(
                'Not found order with id @order_id.',
                ['@order_id' => $orderId]
            );
            throw new BadRequestHttpException();
            return false;
        }
        return $order;
    }
}
