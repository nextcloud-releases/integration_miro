<?php

namespace OCA\Miro\Tests;

use DateTime;
use OCA\Miro\AppInfo\Application;
use OCA\Miro\Service\MiroAPIService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MiroAPIServiceTest extends TestCase {

	/**
	 * @var LoggerInterface|MockObject
	 */
	private $logger;
	/**
	 * @var IL10N|MockObject
	 */
	private $l10n;
	/**
	 * @var IConfig|MockObject
	 */
	private $config;
	/**
	 * @var IClientService|MockObject
	 */
	private $clientService;
	/**
	 * @var IClient|MockObject
	 */
	private $client;
	/**
	 * @var MiroAPIService
	 */
	private $service;

	/**
	 * @var IResponse|MockObject
	 */
	private $emptyResponse;

	/**
	 * @var IResponse|MockObject
	 */
	private $imageResponse;

	/**
	 * @var IResponse|MockObject
	 */
	private $jsonResponse;

	/**
	 * @var IResponse|MockObject
	 */
	private $errorResponse;

	/**
	 * @var IResponse|MockObject
	 */
	private $boardsResponse;

	/**
	 * @var IResponse|MockObject
	 */
	private $boardResponse;

	private $defaultOptions = [
		'headers' => [
			'Authorization' => 'Bearer secret token',
			'Accept' => 'application/json',
			'User-Agent' => Application::INTEGRATION_USER_AGENT,
		],
	];

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);

		$this->clientService->expects($this->once())->method('newClient')->willReturn($this->client);

		$userValues = [];
		for ($i = 1; $i <= 3; $i++) {
			$user = 'user' . $i;
			$team = 'team' . $i;
			$userValues = array_merge($userValues, [
				[$user, Application::APP_ID, 'refresh_token', '', 'secret refresh token'],
				[$user, Application::APP_ID, 'token_expires_at', '', (new Datetime())->getTimestamp() + 3600],
				[$user, Application::APP_ID, 'token', '', 'secret token'],
				[$user, Application::APP_ID, 'team_id', '', $team],
			]);
		}
		$this->config->method('getUserValue')->willReturnMap($userValues);

		$this->emptyResponse = $this->createMock(IResponse::class);
		$this->emptyResponse->method('getStatusCode')->willReturn(200);
		$this->emptyResponse->method('getBody')->willReturn([]);

		$this->imageResponse = $this->createMock(IResponse::class);
		$this->imageResponse->method('getStatusCode')->willReturn(200);
		$this->imageResponse->method('getBody')->willReturn('image');

		$this->jsonResponse = $this->createMock(IResponse::class);
		$this->jsonResponse->method('getStatusCode')->willReturn(200);
		$this->jsonResponse->method('getBody')->willReturn('{"id": "miro"}');

		$this->errorResponse = $this->createMock(IResponse::class);
		$this->errorResponse->method('getStatusCode')->willReturn(400);
		$this->l10n->method('t')->with('Bad credentials', [])->willReturn('error');

		$this->boardsResponse = $this->createMock(IResponse::class);
		$this->boardsResponse->method('getStatusCode')->willReturn(200);
		$this->boardsResponse->method('getBody')->willReturn('{"data": [{"createdBy": {"name": "miro"}}]}');

		$this->boardResponse = $this->createMock(IResponse::class);
		$this->boardResponse->method('getStatusCode')->willReturn(200);
		$this->boardResponse->method('getBody')->willReturn('{"createdBy": {"name": "miro"}}');

		$this->service = new MiroAPIService(
			$this->logger,
			$this->l10n,
			$this->config,
			$this->clientService,
		);
	}

	public function testGetUserAvatar() {
		$this->client->expects($this->exactly(6))->method('get')->willReturnMap([
			[Application::MIRO_API_BASE_URL . '/users/user1_miro/image', $this->defaultOptions, $this->imageResponse],
			[Application::MIRO_API_BASE_URL . '/users/user2_miro/image', $this->defaultOptions, $this->emptyResponse],
			[Application::MIRO_API_BASE_URL . '/users/user2_miro/image/default', $this->defaultOptions, $this->imageResponse],
			[Application::MIRO_API_BASE_URL . '/users/user3_miro/image', $this->defaultOptions, $this->emptyResponse],
			[Application::MIRO_API_BASE_URL . '/users/user3_miro/image/default', $this->defaultOptions, $this->emptyResponse],
			[Application::MIRO_API_BASE_URL . '/users/user3_miro', $this->defaultOptions, $this->jsonResponse],
		]);

		$this->assertEquals(['avatarContent' => 'image'], $this->service->getUserAvatar('user1', 'user1_miro'));
		$this->assertEquals(['avatarContent' => 'image'], $this->service->getUserAvatar('user2', 'user2_miro'));
		$this->assertEquals(['userInfo' => ['id' => 'miro']], $this->service->getUserAvatar('user3', 'user3_miro'));
	}

	public function testGetTeamAvatar() {
		$this->client->expects($this->exactly(3))->method('get')->willReturnMap([
			[Application::MIRO_API_BASE_URL . '/teams/team1/image', $this->defaultOptions, $this->imageResponse],
			[Application::MIRO_API_BASE_URL . '/teams/team2/image', $this->defaultOptions, $this->emptyResponse],
			[Application::MIRO_API_BASE_URL . '/teams/team2', $this->defaultOptions, $this->jsonResponse],
		]);

		$this->assertEquals(['avatarContent' => 'image'], $this->service->getTeamAvatar('user1', 'team1'));
		$this->assertEquals(['teamInfo' => ['id' => 'miro']], $this->service->getTeamAvatar('user2', 'team2'));
	}

	public function testGetMyBoards() {
		$this->client->expects($this->exactly(2))->method('get')->willReturnMap([
			[Application::MIRO_API_BASE_URL . '/v2/boards?team_id=team1&limit=50&sort=last_modified', $this->defaultOptions, $this->errorResponse],
			[Application::MIRO_API_BASE_URL . '/v2/boards?team_id=team2&limit=50&sort=last_modified', $this->defaultOptions, $this->boardsResponse],
		]);

		$this->assertEquals(['error' => 'error'], $this->service->getMyBoards('user1'));
		$this->assertEquals([['createdBy' => ['name' => 'miro'], 'createdByName' => 'miro', 'trash' => false]], $this->service->getMyBoards('user2'));
	}

	public function testCreateBoard() {
		$baseParams = [
			'permissionPolicy' => [
				'collaborationToolsStartAccess' => 'all_editors',
				'copyAccess' => 'anyone',
				'sharingAccess' => 'team_members_with_editing_rights',
			],
			'sharingPolicy' => [
				'access' => 'edit',
				'inviteToAccountAndBoardLinkAccess' => 'editor',
				'organizationAccess' => 'edit',
				'teamAccess' => 'edit',
			],
		];
		$this->client->expects($this->exactly(2))->method('post')->willReturnMap([
			[Application::MIRO_API_BASE_URL . '/v2/boards', array_merge($this->defaultOptions, ['json' => array_merge(['name' => 'board1', 'description' => 'description1', 'teamId' => 'team1'], $baseParams)]), $this->errorResponse],
			[Application::MIRO_API_BASE_URL . '/v2/boards', array_merge($this->defaultOptions, ['json' => array_merge(['name' => 'board2', 'description' => 'description2', 'teamId' => 'team2'], $baseParams)]), $this->boardResponse],
		]);

		$this->assertEquals(['error' => 'error'], $this->service->createBoard('user1', 'board1', 'description1', 'team1'));
		$this->assertEquals(['createdBy' => ['name' => 'miro'], 'createdByName' => 'miro', 'trash' => false], $this->service->createBoard('user2', 'board2', 'description2', 'team2'));
	}

	public function testDeleteBoard() {
		$this->client->expects($this->exactly(1))->method('delete')->willReturnMap([
			[Application::MIRO_API_BASE_URL . '/v2/boards/board1', $this->defaultOptions, $this->jsonResponse],
		]);

		$this->assertEquals(['id' => 'miro'], $this->service->deleteBoard('user1', 'board1'));
	}
}
