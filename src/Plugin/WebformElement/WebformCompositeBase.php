<?php

namespace Drupal\webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element as RenderElement;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webform\Entity\WebformOptions;
use Drupal\webform\WebformElementBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a base for composite elements.
 */
abstract class WebformCompositeBase extends WebformElementBase {

  use WebformCompositeTrait;

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    $properties = [
        'title' => '',
        'multiple' => FALSE,
        'multiple__header' => FALSE,
        'multiple__header_label' => '',
        // General settings.
        'description' => '',
        'default_value' => [],
        // Form display.
        'title_display' => 'invisible',
        'description_display' => '',
        // Form validation.
        'required' => FALSE,
        // Flex box.
        'flexbox' => '',
      ] + $this->getDefaultBaseProperties();

    $composite_elements = $this->getCompositeElements();
    foreach ($composite_elements as $composite_key => $composite_element) {
      // Get #type, #title, and #option from composite elements.
      foreach ($composite_element as $composite_property_key => $composite_property_value) {
        if (in_array($composite_property_key, ['#type', '#title', '#options'])) {
          $property_key = str_replace('#', $composite_key . '__', $composite_property_key);
          if ($composite_property_value instanceof TranslatableMarkup) {
            $properties[$property_key] = (string) $composite_property_value;
          }
          else {
            $properties[$property_key] = $composite_property_value;
          }
        }
      }
      if (isset($properties[$composite_key . '__type'])) {
        $properties['default_value'][$composite_key] = '';
        $properties[$composite_key . '__description'] = FALSE;
        $properties[$composite_key . '__required'] = FALSE;
        $properties[$composite_key . '__placeholder'] = '';
      }
      $properties[$composite_key . '__access'] = TRUE;
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission) {
    parent::prepare($element, $webform_submission);

    // If #flexbox is not set or an empty string, determine if the
    // webform is using a flexbox layout.
    if (!isset($element['#flexbox']) || $element['#flexbox'] === '') {
      $webform = $webform_submission->getWebform();
      $element['#flexbox'] = $webform->hasFlexboxLayout();
    }
  }

  /**
   * Set multiple element wrapper.
   *
   * @param array $element
   *   An element.
   */
  protected function prepareMultipleWrapper(array &$element) {
    if (empty($element['#multiple']) || !$this->supportsMultipleValues()) {
      return;
    }

    parent::prepareMultipleWrapper($element);

    if (!empty($element['#multiple__header'])) {
      $element['#header'] = TRUE;
      $element = $this->getInitializedCompositeElement($element);
      foreach (Element::children($element) as $key) {
        $element['#element'][$key] = $element[$key];
        $element['#element'][$key]['#title_display'] = 'invisible';
        unset($element[$key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Update #default_value description.
    $form['element']['default_value']['#description'] = $this->t("The default value of the composite webform element as YAML.");

    // Update #required label.
    $form['validation']['required']['#description'] .= '<br/>' . $this->t("Checking this option only displays the required indicator next to this element's label. Please chose which elements should be required below.");

    // Update '#multiple__header_label'.
    $form['element']['multiple__header_label']['#states']['visible'][':input[name="properties[multiple__header]"]'] = ['checked' => FALSE];

    $form['composite'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('@title settings', ['@title' => $this->getPluginLabel()]),
    ];
    $form['composite']['elements'] = $this->buildCompositeElementsTable();
    $form['composite']['flexbox'] = [
      '#type' => 'select',
      '#title' => $this->t('Use Flexbox'),
      '#description' => $this->t("If 'Automatic' is selected Flexbox layout will only be used if a Flexbox element is included in the webform."),
      '#options' => [
        '' => $this->t('Automatic'),
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
    ];
    return $form;
  }

  /**
   * Build the composite elements settings table.
   *
   * @return array
   *   A renderable array container the composite elements settings table.
   */
  protected function buildCompositeElementsTable() {
    $header = [
      $this->t('Key'),
      $this->t('Title/Description/Placeholder'),
      $this->t('Type/Options'),
      $this->t('Required'),
      $this->t('Visible'),
    ];

    $rows = [];
    $composite_elements = $this->getCompositeElements();
    foreach ($composite_elements as $composite_key => $composite_element) {
      $title = (isset($composite_element['#title'])) ? $composite_element['#title'] : $composite_key;
      $type = isset($composite_element['#type']) ? $composite_element['#type'] : NULL;
      $t_args = ['@title' => $title];
      $attributes = ['style' => 'width: 100%; margin-bottom: 5px'];
      $state_disabled = [
        'disabled' => [
          ':input[name="properties[' . $composite_key . '__access]"]' => [
            'checked' => FALSE,
          ],
        ],
      ];

      $row = [];

      // Key.
      $row[$composite_key . '__key'] = [
        '#markup' => $composite_key,
        '#access' => TRUE,
      ];

      // Title, placeholder, and description.
      if ($type) {
        $row['title_and_description'] = [
          'data' => [
            $composite_key . '__title' => [
              '#type' => 'textfield',
              '#title' => $this->t('@title title', $t_args),
              '#title_display' => 'invisible',
              '#placeholder' => $this->t('Enter title...'),
              '#attributes' => $attributes,
              '#states' => $state_disabled,
            ],
            $composite_key . '__placeholder' => [
              '#type' => 'textfield',
              '#title' => $this->t('@title placeholder', $t_args),
              '#title_display' => 'invisible',
              '#placeholder' => $this->t('Enter placeholder...'),
              '#attributes' => $attributes,
              '#states' => $state_disabled,
            ],
            $composite_key . '__description' => [
              '#type' => 'textarea',
              '#title' => $this->t('@title description', $t_args),
              '#title_display' => 'invisible',
              '#rows' => 2,
              '#placeholder' => $this->t('Enter description...'),
              '#attributes' => $attributes,
              '#states' => $state_disabled,
            ],
          ],
        ];
      }
      else {
        $row['title_and_description'] = ['data' => ['']];
      }

      // Type and options.
      // Using if/else instead of switch/case because of complex conditions.
      $row['type_and_options'] = [];
      if ($type == 'tel') {
        $row['type_and_options']['data'][$composite_key . '__type'] = [
          '#type' => 'select',
          '#required' => TRUE,
          '#options' => [
            'tel' => $this->t('Telephone'),
            'textfield' => $this->t('Text field'),
          ],
          '#attributes' => ['style' => 'width: 100%; margin-bottom: 5px'],
          '#states' => $state_disabled,
        ];
      }
      elseif ($type == 'select' && ($composite_options = $this->getCompositeElementOptions($composite_key))) {
        $row['type_and_options']['data'][$composite_key . '__type'] = [
          '#type' => 'select',
          '#required' => TRUE,
          '#options' => [
            'select' => $this->t('Select'),
            'webform_select_other' => $this->t('Select other'),
            'textfield' => $this->t('Text field'),
          ],
          '#attributes' => ['style' => 'width: 100%; margin-bottom: 5px'],
          '#states' => $state_disabled,
        ];
        $row['type_and_options']['data'][$composite_key . '__options'] = [
          '#type' => 'select',
          '#options' => $composite_options,
          '#required' => TRUE,
          '#attributes' => ['style' => 'width: 100%;'],
          '#states' => $state_disabled + [
            'invisible' => [
              ':input[name="properties[' . $composite_key . '__type]"]' => [
                'value' => 'textfield',
              ],
            ],
          ],
        ];
      }
      else {
        $row['type_and_options']['data'][$composite_key . '__type'] = [
          '#type' => 'textfield',
          '#access' => FALSE,
        ];
        $row['type_and_options']['data']['markup'] = [
          '#markup' => $this->elementManager->getElementInstance($composite_element)->getPluginLabel(),
          '#access' => TRUE,
        ];
      }

      // Required.
      if ($type) {
        $row[$composite_key . '__required'] = [
          '#type' => 'checkbox',
          '#return_value' => TRUE,
        ];
      }
      else {
        $row[$composite_key . '__required'] = ['data' => ['']];
      }

      // Access.
      $row[$composite_key . '__access'] = [
        '#type' => 'checkbox',
        '#return_value' => TRUE,
      ];

      $rows[$composite_key] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $header,
    ] + $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestValues(array $element, WebformInterface $webform, array $options = []) {
    /** @var \Drupal\webform\WebformSubmissionGenerateInterface $generate */
    $generate = \Drupal::service('webform_submission.generate');

    $composite_elements = $this->getInitializedCompositeElement($element);
    $values = [];
    for ($i = 1; $i <= 3; $i++) {
      $value = [];
      foreach (RenderElement::children($composite_elements) as $composite_key) {
        $value[$composite_key] = $generate->getTestValue($webform, $composite_key, $composite_elements[$composite_key], $options);
      }
      $values[] = $value;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFormProperties(array &$form, FormStateInterface $form_state) {
    $properties = parent::getConfigurationFormProperties($form, $form_state);
    foreach ($properties as $key => $value) {
      // Convert composite element access and required to boolean value.
      if (strpos($key, '__access') || strpos($key, '__required')) {
        $properties[$key] = (boolean) $value;
      }
      // If the entire element is required remove required property for
      // composite elements.
      if (!empty($properties['required']) && strpos($key, '__required')) {
        unset($properties[$key]);
      }
    }
    return $properties;
  }

  /**
   * Get webform option keys for composite element based on the composite element's key.
   *
   * @param string $composite_key
   *   A composite element's key.
   *
   * @return array
   *   An array webform options.
   */
  protected function getCompositeElementOptions($composite_key) {
    /** @var \Drupal\webform\WebformOptionsInterface[] $webform_options */
    $webform_options = WebformOptions::loadMultiple();
    $options = [];
    foreach ($webform_options as $key => $webform_option) {
      if (strpos($key, $composite_key) === 0) {
        $options[$key] = $webform_option->label();
      }
    }
    return $options;
  }

}
