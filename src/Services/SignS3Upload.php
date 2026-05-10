<?php

namespace DnskWork\TwillR2\Services;

use A17\Twill\Services\Uploader\SignUploadListener;
use Illuminate\Config\Repository as Config;

/**
 * Fixes Twill's SignS3Upload policy validation: the original compares parsedMaxSize
 * against (string)null which always fails when Fine Uploader includes a
 * content-length-range condition. This implementation validates by checking that the
 * bucket condition matches the configured bucket instead.
 */
class SignS3Upload
{
    private string $bucket;

    private string $secret;

    public function __construct(protected Config $config) {}

    public function fromPolicy($policy, SignUploadListener $listener, $disk = 'libraries')
    {
        $policyObject = json_decode($policy, true);
        $policyJson = json_encode($policyObject);

        $this->bucket = (string) $this->config->get('filesystems.disks.' . $disk . '.bucket');
        $this->secret = (string) $this->config->get('filesystems.disks.' . $disk . '.secret');

        $signedPolicy = $this->signPolicy($policyJson);

        if ($signedPolicy) {
            return $listener->uploadIsSigned($signedPolicy);
        }

        return $listener->uploadIsNotValid();
    }

    private function signPolicy($policyJson)
    {
        $policyObject = json_decode($policyJson, true);

        if ($this->isValid($policyObject)) {
            $encodedPolicy = base64_encode($policyJson);

            return [
                'policy'    => $encodedPolicy,
                'signature' => $this->signV4Policy($policyObject, $encodedPolicy),
            ];
        }

        return null;
    }

    private function isValid(array $policy): bool
    {
        $bucket = null;

        foreach ($policy['conditions'] as $condition) {
            if (isset($condition['bucket'])) {
                $bucket = $condition['bucket'];
            }
        }

        return $bucket === $this->bucket;
    }

    private function signV4Policy(array $policy, string $encodedPolicy): string
    {
        $credentialCondition = '';

        foreach ($policy['conditions'] as $condition) {
            if (isset($condition['x-amz-credential'])) {
                $credentialCondition = $condition['x-amz-credential'];
            }
        }

        preg_match("/.+\/(.+)\\/(.+)\/s3\/aws4_request/", $credentialCondition, $matches);

        $dateKey              = hash_hmac('sha256', $matches[1], 'AWS4' . $this->secret, true);
        $dateRegionKey        = hash_hmac('sha256', $matches[2], $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
        $signingKey           = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

        return hash_hmac('sha256', $encodedPolicy, $signingKey);
    }
}
