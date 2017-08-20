<?php

declare(strict_types=1);

/*
 * This file is part of CSBill project.
 *
 * (c) 2013-2017 Pierre du Plessis <info@customscripts.co.za>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace CSBill\InvoiceBundle\Action;

use CSBill\CoreBundle\Response\FlashResponse;
use CSBill\CoreBundle\Traits\SaveableTrait;
use CSBill\InvoiceBundle\Entity\Invoice;
use CSBill\InvoiceBundle\Exception\InvalidTransitionException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Workflow\StateMachine;

final class Transition
{
    use SaveableTrait;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var StateMachine
     */
    private $stateMachine;

    public function __construct(RouterInterface $router, StateMachine $stateMachine)
    {
        $this->router = $router;
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(Request $request, string $action, Invoice $invoice)
    {
        if (!$this->stateMachine->can($invoice, $action)) {
            throw new InvalidTransitionException($action);
        }

        $this->stateMachine->apply($invoice, $action);

        $this->save($invoice);

        $route = $this->router->generate('_invoices_view', ['id' => $invoice->getId()]);

        return new class($action, $route) extends RedirectResponse implements FlashResponse {
            /**
             * @var string
             */
            private $action;

            public function __construct(string $action, string $route)
            {
                $this->action = $action;
                parent::__construct($route);
            }

            public function getFlash(): iterable
            {
                yield self::FLASH_SUCCESS => 'invoice.transition.action.'.$this->action;
            }
        };
    }
}