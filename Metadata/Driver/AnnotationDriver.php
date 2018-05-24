<?php

namespace Vich\UploaderBundle\Metadata\Driver;

use Doctrine\Common\Annotations\Reader as AnnotationReader;
use Doctrine\ORM\Mapping\Embedded;
use Metadata\Driver\AdvancedDriverInterface;
use ReflectionProperty;
use Vich\UploaderBundle\Mapping\Annotation\Uploadable;
use Vich\UploaderBundle\Mapping\Annotation\UploadableField;
use Vich\UploaderBundle\Metadata\ClassMetadata;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 * @author Konstantin Myakshin <koc-dp@yandex.ru>
 */
class AnnotationDriver implements AdvancedDriverInterface
{
    /**
     * @deprecated
     */
    const UPLOADABLE_ANNOTATION = Uploadable::class;

    /**
     * @deprecated
     */
    const UPLOADABLE_FIELD_ANNOTATION = UploadableField::class;

    protected $reader;

    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    public function loadMetadataForClass(\ReflectionClass $class)
    {
        if (!$this->isUploadable($class)) {
            return;
        }

        $classMetadata = new ClassMetadata($class->name);
        $classMetadata->fileResources[] = $class->getFileName();

        $this->loadUploadableFields($class, $classMetadata);

        return $classMetadata;
    }

    public function getAllClassNames()
    {
        return [];
    }

    protected function isUploadable(\ReflectionClass $class)
    {
        return null !== $this->reader->getClassAnnotation($class, Uploadable::class);
    }

    private function loadUploadableFields(\ReflectionClass $class, ClassMetadata $classMetadata, string $parentProperty = '')
    {
        foreach ($class->getProperties() as $property) {
            $uploadableField = $this->reader->getPropertyAnnotation($property, UploadableField::class);

            if ($uploadableField instanceof UploadableField) {
                $this->addFieldMetadata($classMetadata, $uploadableField, $property, $parentProperty);
            } else {
                // Check for UploadableField in Embedded
                $embeddedField = $this->reader->getPropertyAnnotation($property, Embedded::class);
                if ($embeddedField instanceof Embedded) {
                    $embeddedClass = new \ReflectionClass($embeddedField->class);

                    // Override ClassMetadata name with the Embedded one.
                    $classMetadata->name = $embeddedClass->name;
                    $classMetadata->fileResources[] = $embeddedClass->getFileName();

                    $parentProperty .= $parentProperty ? $parentProperty . '.' . $property->getName() : $property->getName();

                    $this->loadUploadableFields($embeddedClass, $classMetadata, $parentProperty);
                }
            }
        }
    }

    private function addFieldMetadata(ClassMetadata $classMetadata, UploadableField $uploadableField, \ReflectionProperty $property, string $parentProperty = '')
    {
        $fieldMetadata = [
            'mapping' => $uploadableField->getMapping(),
            'propertyName' => $parentProperty ? $parentProperty . '.' . $property->getName() : $property->getName(),
            'fileNameProperty' => $uploadableField->getFileNameProperty(),
            'size' => $uploadableField->getSize(),
            'mimeType' => $uploadableField->getMimeType(),
            'originalName' => $uploadableField->getOriginalName(),
            'dimensions' => $uploadableField->getDimensions(),
        ];

        //TODO: store UploadableField object instead of array
        $classMetadata->fields[$fieldMetadata['propertyName']] = $fieldMetadata;
    }
}
