<?php

/*
 * This file is part of the Sonata project.
 *
 * (c) Sonata Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Resizer;

use Imagine\Image\ImagineInterface;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\Color;
use Gaufrette\File;
use Sonata\MediaBundle\Model\MediaInterface;
use Imagine\Image\ImageInterface;
use Imagine\Exception\InvalidArgumentException;

/**
 * This reziser crop the image when the width and height are specified by default.
 *
 * @author Maxim Lovchikov <maxim.lovchikov@gmail.com>
 */
class AdvancedResizer implements ResizerInterface
{
    protected $adapter;
    protected $mode;

    /**
     * @param \Imagine\Image\ImagineInterface $adapter
     * @param string                          $mode
     */
    public function __construct(ImagineInterface $adapter, $mode)
    {
        $this->adapter = $adapter;
        $this->mode    = $mode;
        
        if ($this->mode !== ImageInterface::THUMBNAIL_INSET && $this->mode !== ImageInterface::THUMBNAIL_OUTBOUND) {
        	throw new InvalidArgumentException('Invalid mode specified');
        }
    }

    /**
     * Make thumb for media.
     * 
     * {@inheritdoc}
     */
    public function resize(MediaInterface $media, File $in, File $out, $format, array $settings)
    {
        self::validateSettings($media, $settings);
        $settings = $this->setMode($settings);
        
        $image = $this->adapter->load($in->getContent());
        $imageSize = $image->getSize();
        
        if ($settings['crop'] === true) {
        	
        	$size = $this->computeBox($media, $settings);
        	
        	if ($size->getWidth() != $imageSize->getWidth() || $size->getHeight() != $imageSize->getHeight()) {
        		$image = $image->resize($size);
        	}
        	
        	if ($size->getWidth() != $settings['width'] || $size->getHeight() != $settings['height']) {
	        	$x = ceil(abs($size->getWidth() - $settings['width'])/2);
	        	$y = ceil(abs($size->getHeight() - $settings['height'])/2);
		        $image = $image->crop(new Point($x, $y), $this->getBox($media, $settings));
        	}
	        
        } elseif (!empty($settings['fill'])) {
        	
        	try{
        		$color = new Color($settings['fill']);
        	} catch (\Exception $e) {
        		$color = null;
        	}
        	
        	$size = $this->computeBox($media, $settings);
        	
        	if ($size->getWidth() != $imageSize->getWidth() || $size->getHeight() != $imageSize->getHeight()) {
        		$image = $image->resize($size);
        	}
        	
        	$bgSize = $this->getBox($media, $settings);
        	
        	if ($size->getWidth() != $bgSize->getWidth() || $size->getHeight() != $bgSize->getHeight()) {
        		$bg = $this->adapter->create($bgSize, $color);
        		$x = ceil(abs($bgSize->getWidth() - $size->getWidth())/2);
        		$y = ceil(abs($bgSize->getHeight() - $size->getHeight())/2);
        		$image = $bg->paste($image, new Point($x, $y));
        	}
        } else {
        	
        	$size = $this->getBox($media, $settings);
        	if ($size->getWidth() != $imageSize->getWidth() || $size->getHeight() != $imageSize->getHeight()) {
        		$image = $image->resize($size);
        	}
        }
        
        $out->setContent($image->get($format, array('quality' => $settings['quality'])));
    }

    /**
     * Get final thumb dimensions.
     * 
     * {@inheritdoc}
     */
    public function getBox(MediaInterface $media, array $settings)
    {
    	self::validateSettings($media, $settings);
    	
    	$settings = $this->setMode($settings);
    	
		if ($settings['crop'] === true || !empty($settings['fill'])) {
			return new Box($settings['width'],$settings['height']);
		}	  
    	
        return $this->computeBox($media, $settings);
    }
    
    /**
     * Modify current resize mode depending on 'crop' and 'fill' setting values.
     * 
     * @param array $settings
     * @return array
     */
    private function setMode (array $settings)
    {
    	if ($settings['crop'] === true) {
    		$settings['mode'] = ImageInterface::THUMBNAIL_OUTBOUND;
    	} elseif (!empty($settings['fill'])) {
    		$settings['mode'] = ImageInterface::THUMBNAIL_INSET;
    	} else {
    		$settings['mode'] = $this->mode;
    	} 
    	
    	return $settings;
    }
    
    /**
     * Validate given settings set.
     * 
     * @param MediaInterface $media
     * @param array $settings
     * @throws \RuntimeException
     * @return boolean
     */
    static private function validateSettings(MediaInterface $media, array $settings)
    {
    	if (empty($settings['width']) && empty($settings['height'])) {
    		throw new \RuntimeException(sprintf('Width or height parameter must be determined in context "%s" for provider "%s"', $media->getContext(), $media->getProviderName()));
    	} elseif ($settings['crop'] === true && (empty($settings['width']) || empty($settings['height']) || !empty($settings['fill']) )) {
		    throw new \RuntimeException(sprintf('For crop mode width and height parameter must be determined, fill must be null(or false) in context "%s" for provider "%s"', $media->getContext(), $media->getProviderName()));
    	} elseif (!empty($settings['fill']) && ($settings['crop'] !== false || empty($settings['width']) || empty($settings['height']) )) {
    		throw new \RuntimeException(sprintf('For fill mode width and height parameter must be determined, crop must be false in context "%s" for provider "%s"', $media->getContext(), $media->getProviderName()));
    	}
    	
    	return true;
    }

    /**
     * Calculate thumb size by scaling a media depending on current settings. It's not a final thumb size.
     * 
     * @param MediaInterface $media
     * @param array $settings
     * @return \Imagine\Image\Box
     */
    private function computeBox(MediaInterface $media, array $settings)
    {
        $size = $media->getBox();
        $ratios = array();
        
        if (!empty($settings['width'])) {
        	$ratios[] = $settings['width'] / $size->getWidth();
        }
        
        if (!empty($settings['height'])) {
        	$ratios[] = $settings['height'] / $size->getHeight();
        }
        
        if ($settings['mode'] === ImageInterface::THUMBNAIL_INSET) {
            $ratio = min($ratios);
        } else {
            $ratio = max($ratios);
        }
        
        $size = $size->scale($ratio);
        
        return $size; 
    }
}