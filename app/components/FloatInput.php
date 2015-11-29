<?php

namespace Spisovka\Controls;

/**
 * Control pro vstup čísel. Používá napevno desetinnou čárku.
 *
 * @author Pavel Laštovička
 */
class FloatInput extends \Nette\Forms\Controls\TextInput
{
    public function __construct($label = NULL, $maxLength = NULL)
    {
        parent::__construct($label, $maxLength);
        $this->addCondition(\Nette\Forms\Form::FILLED)
            ->addRule(\Nette\Forms\Form::FLOAT);
    }
    
	/**
	 * Sets control's value.
	 * @param  int|float|string
	 * @return self
	 */
	public function setValue($value)
	{
        $value = str_replace('.', ',', $value);
        return parent::setValue($value);
    }
    
	/**
	 * Returns control's value.
	 * @return float|null
	 */
    public function getValue()
    {
        $value = parent::getValue();
        if (empty($value))
            return null;
        
        $value = str_replace(',', '.', $value);
        return floatval($value);
    }
}
