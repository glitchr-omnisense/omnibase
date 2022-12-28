<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Base\Form\Extension;

use Base\Enum\SpamScore;
use Base\Routing\RouterInterface;
use Base\Service\Model\SpamProtectionInterface;

use Base\Service\SpamChecker;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormTypeSpamExtension extends AbstractTypeExtension
{
    /**
     * @var SpamChecker
     */
    protected $spamChecker;
    
    /**
     * @var Router
     */
    protected $router;
    
    /** @var bool */
    private bool $defaultEnabled;

    public function __construct(SpamChecker $spamChecker, RouterInterface $router, bool $defaultEnabled = true)
    {
        $this->spamChecker      = $spamChecker;
        $this->defaultEnabled   = $defaultEnabled;
        $this->router           = $router;
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'spam_protection' => ($this->defaultEnabled && !$this->router->isEasyAdmin())
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options["spam_protection"]) return;
        if (!$builder->getForm()->isRoot()) return;

        $hasSpamInterface = class_implements_interface($options["data_class"] ?? null, SpamProtectionInterface::class);
        if (!$hasSpamInterface ) return;

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $score = $this->spamChecker->check($data);

            $enum = SpamScore::__toInt();
            switch($score) {

                default:
                case $enum[SpamScore::NOT_SPAM]:
                case $enum[SpamScore::MAYBE_SPAM]:
                    break;

                case $enum[SpamScore::BLATANT_SPAM]:
                    $form->addError(new FormError('Blatant spam, go away!'));
            }
        });
    }
}
