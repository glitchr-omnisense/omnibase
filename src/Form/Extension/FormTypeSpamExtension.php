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

use Base\Entity\Thread;
use Base\Entity\User\Notification;
use Base\Enum\SpamScore;
use Base\Model\SpamProtectionInterface;
use Base\Service\BaseService;
use Base\Service\SpamChecker;
use Base\Validator\Constraints\Spam;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Csrf\EventListener\CsrfValidationListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormTypeSpamExtension extends AbstractTypeExtension
{
    private $defaultEnabled;

    public function __construct(SpamChecker $spamChecker, AdminContextProvider $adminContextProvider, bool $defaultEnabled = true)
    {
        $this->spamChecker      = $spamChecker;
        $this->easyadminContext = $adminContextProvider->getContext();
        $this->defaultEnabled   = $defaultEnabled;
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
            'spam_protection' => $this->defaultEnabled && ($this->easyadminContext === null)
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options["spam_protection"]) return;
        if (!$builder->getForm()->isRoot()) return;

        $hasSpamInterface = BaseService::hasInterface($options["data_class"] ?? null, SpamProtectionInterface::class);
        if (!$hasSpamInterface ) return;

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData(); 

            $score = $this->spamChecker->getScore($data);
            $data->getSpamCallback($score);

            switch($score) {

                default:
                case SpamScore::NOT_SPAM:
                case SpamScore::MAYBE_SPAM:
                    break;

                case SpamScore::BLATANT_SPAM:
                    $form->addError(new FormError('Blatant spam, go away!'));
            }
        });
    }
}
