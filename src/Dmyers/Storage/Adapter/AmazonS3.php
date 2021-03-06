<?php namespace Dmyers\Storage\Adapter;

use \Aws\S3\S3Client;

class AmazonS3 extends Base
{
	protected $name = 'amazons3';
	protected $client;
	
	public function __construct()
	{
		$key = $this->config('key');
		
		if (empty($key)) {
			throw new \RuntimeException('AmazonS3 key config required.');
		}
		
		$secret = $this->config('secret');
		
		if (empty($secret)) {
			throw new \RuntimeException('AmazonS3 secret config required.');
		}
		
		$bucket = $this->config('bucket');
		
		if (empty($bucket)) {
			throw new \RuntimeException('AmazonS3 bucket config required.');
		}
		
		$this->bucket = $bucket;
		
		$this->client = S3Client::factory(array(
			'key'    => $key,
			'secret' => $secret,
		));
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function exists($path)
	{
		return $this->client->doesObjectExist($this->bucket, $this->computePath($path));
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function get($path)
	{
		return (string) $this->client->getObject(array(
			'Bucket' => $this->bucket,
			'Key'    => $this->computePath($path),
		))->get('Body');
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function put($path, $contents)
	{
		return $this->client->putObject(array(
			'Bucket' => $this->bucket,
			'Key'    => $this->computePath($path),
			'Body'   => $contents,
			'ACL'    => $this->config('acl', 'public-read'),
		));
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function upload($path, $target)
	{
		return $this->client->putObject(array(
			'Bucket'     => $this->bucket,
			'Key'        => $this->computePath($target),
			'SourceFile' => $path,
			'ACL'        => $this->config('acl', 'public-read'),
		));
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function delete($path)
	{
		return $this->client->deleteObject(array(
			'Bucket' => $this->bucket,
			'Key'    => $this->computePath($path),
		));
	}
	
	public function move($path, $target)
	{
		$this->copy($path, $target);
		$this->delete($path);
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function copy($path, $target)
	{
		return $this->client->putObject(array(
			'Bucket'     => $this->bucket,
			'Key'        => $target,
			'CopySource' => $this->computePath($path),
			'ACL'        => $this->config('acl', 'public-read'),
		));
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function type($path)
	{
		if ($this->isDirectory($path)) {
			return 'dir';
		}
		
		return 'file';
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function mime($path)
	{
		$object = $this->client->headObject(array(
			'Bucket' => $this->bucket,
			'Key'    => $this->computePath($path),
		));
		
		return $object['ContentType'];
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function size($path)
	{
		$object = $this->client->headObject(array(
			'Bucket' => $this->bucket,
			'Key'    => $this->computePath($path),
		));
		
		return $object['ContentLength'];
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function lastModified($path)
	{
		$object = $this->client->headObject(array(
			'Bucket' => $this->bucket,
			'Key'    => $this->computePath($path),
		));
		
		return $object['LastModified'];
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function isDirectory($path)
	{
		$result = $this->client->listObjects(array(
			'Bucket'  => $this->bucket,
			'Prefix'  => $this->computePath($path),
			'Maxkeys' => 1,
		));
		
		return count($result) > 0;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function files($path)
	{
		return $this->client->listKeys(array(
			'Bucket' => $this->bucket,
			'Prefix' => $this->computePath($path),
		));
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function url($path)
	{
		return $this->client->getObjectUrl($this->bucket, $this->computePath($path));
	}
	
	protected function ensureBucketExists()
	{
		if (!$this->bucketExists()) {
			$client->createBucket(array(
				'Bucket' => $bucket,
			));
			
			$client->waitUntilBucketExists(array(
				'Bucket' => $bucket,
			));
		}
	}
	
	protected function bucketExists()
	{
		return $this->client->doesBucketExist($this->bucket);
	}
	
	protected function computePath($path)
	{
		$this->ensureBucketExists();
		
		return S3Client::encodeKey($path);
	}
}