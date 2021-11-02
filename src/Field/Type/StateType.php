<?php

namespace Base\Field\Type;

use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StateType extends AbstractType implements SelectTypeInterface
{
    use SelectTypeTrait;

    public static function getChoices(): array
    {
        return [
            "Published" => ThreadState::PUBLISHED,
            "Draft"     => ThreadState::DRAFT,
            "Future"    => ThreadState::FUTURE,
            "Private"   => ThreadState::SECRET,

            "Approved"  => ThreadState::APPROVED,
            "Pending"   => ThreadState::PENDING,
            "Rejected"  => ThreadState::REJECTED,
            "Deleted"   => ThreadState::DELETED
        ];
    }

    public static function getIcons(): array
    {
        return [
            ThreadState::PUBLISHED => "fas fa-fw fa-eye",
            ThreadState::DRAFT     => "fas fa-fw fa-drafting-compass",
            ThreadState::FUTURE    => "fas fa-fw fa-spinner",
            ThreadState::SECRET    => "fas fa-fw fa-eye-slash",

            ThreadState::APPROVED  => "fas fa-fw fa-check-circle",
            ThreadState::PENDING   => "fas fa-fw fa-pause-circle",
            ThreadState::REJECTED  => "fas fa-fw fa-times-circle",
            ThreadState::DELETED   => "fas fa-fw fa-ban",
        ];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => self::getChoices(),
            'choice_icons' => self::getIcons(),
            'choice_attr' => function (?string $entry) {
                return $entry ? ['data-icon' => self::getIcons()[$entry]] : [];
            },
            'empty_data'   => ThreadState::DRAFT,
            'invalid_message' => function (Options $options, $previousValue) {
                return ($options['legacy_error_messages'] ?? true)
                    ? $previousValue
                    : 'Please select a status.';
            }
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return SelectType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'status';
    }
}