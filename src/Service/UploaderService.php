<?php

namespace CreamIO\UploadBundle\Service;

use CreamIO\UploadBundle\Model\UserStoredFile;
use CreamIO\UserBundle\Exceptions\APIException;
use CreamIO\UserBundle\Service\APIService;
use GBProd\UuidNormalizer\UuidNormalizer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class UploaderService.
 */
class UploaderService
{
    /**
     * @var APIService Injected API service
     */
    private $apiService;

    /**
     * @var string Injected upload directory from config
     */
    private $targetDirectory;

    /**
     * @var ValidatorInterface Injected validator service
     */
    private $validator;

    /**
     * @var string Injected default file upload entity from config
     */
    private $defaultClassToHydrate;

    /**
     * @var string Injected default file field for default file upload entity
     */
    private $defaultClassFileField;

    /**
     * UploaderService constructor.
     *
     * @param string             $targetDirectory
     * @param string             $defaultClassToHydrate
     * @param string             $defaultClassFileField
     * @param APIService         $apiService
     * @param ValidatorInterface $validator
     */
    public function __construct(string $targetDirectory, string $defaultClassToHydrate, string $defaultClassFileField, APIService $apiService, ValidatorInterface $validator)
    {
        $this->apiService = $apiService;
        $this->targetDirectory = $targetDirectory;
        $this->validator = $validator;
        $this->defaultClassToHydrate = $defaultClassToHydrate;
        $this->defaultClassFileField = $defaultClassFileField;
    }

    /**
     * Generates a serializer to denormalize file upload entities.
     *
     * @return Serializer
     */
    public function generateSerializer(): Serializer
    {
        $encoders = [new JsonEncoder()];
        $objectNormalizer = new ObjectNormalizer();
        $normalizers = [new DateTimeNormalizer('d-m-Y H:i:s'), $objectNormalizer, new UuidNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        return $serializer;
    }

    /**
     * Main upload handling method, moves the file and hydrates the file upload entity.
     *
     * @param Request     $request
     * @param bool        $validate
     * @param null|string $classToHydrate
     * @param null|string $fileField
     *
     * @return object File upload entity
     */
    public function handleUpload(Request $request, bool $validate = true, ?string $classToHydrate = null, ?string $fileField = null)
    {
        if (null === $fileField) {
            $fileField = $this->defaultClassFileField;
        }
        if (null === $classToHydrate) {
            $classToHydrate = $this->defaultClassToHydrate;
        }
        $file = $request->files->get('uploaded_file');
        /** @var UploadedFile $file */
        $filename = $this->move($file);
        $postDatas = $request->request->all();
        $postDatas[$fileField] = $filename;
        $uploadedFile = $this->generateSerializer()->denormalize($postDatas, $classToHydrate);
        if ($validate) {
            $this->validateEntity($uploadedFile);
        }

        return $uploadedFile;
    }

    /**
     * Validates the file upload entity.
     *
     * @param UserStoredFile $uploadedFile
     *
     * @throws APIException
     */
    public function validateEntity(UserStoredFile $uploadedFile)
    {
        $validationErrors = $this->validator->validate($uploadedFile);
        if (count($validationErrors) > 0) {
            throw $this->apiService->postError($validationErrors);
        }
    }

    /**
     * Move the uploaded file to the upload directory.
     *
     * @param UploadedFile $file
     *
     * @return string Filename
     */
    public function move(UploadedFile $file): string
    {
        $fileName = md5(uniqid()).'.'.$file->guessExtension();
        $file->move($this->getTargetDirectory(), $fileName);

        return $fileName;
    }

    /**
     * Generated a md5 filename based on uniqid.
     *
     * @return string
     */
    public function generateUniqueFilename(): string
    {
        return md5(uniqid('creamio_', true));
    }

    /**
     * Returns the upload directory.
     *
     * @return string
     */
    public function getTargetDirectory()
    {
        return $this->targetDirectory;
    }
}
