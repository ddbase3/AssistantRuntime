<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 *
 * AssistantRuntime provides the shared execution routing for BASE3 agents.
 * It composes runtime registries, sinks, selectors and configuration services.
 * Agent contracts remain in AssistantFoundation and runtime plugins.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/AssistantRuntime
 **********************************************************************/

namespace Base3\AssistantRuntime\Test\Service;

use Base3\AssistantFoundation\Api\IAgentConfirmableToolSet;
use Base3\AssistantFoundation\Api\IAgentToolSet;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantRuntime\Service\CompositeAgentToolSet;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CompositeAgentToolSetErrorPathsTest extends TestCase {

	public function testBlankToolNameReturnsExplicitFailureWithoutExecutingAnyTool(): void {
		$provider = $this->createMock(IAgentToolSet::class);
		$provider->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('read')]));
		$provider->method('getWarnings')->willReturn([]);
		$provider->expects($this->never())->method('execute');
		$result = (new CompositeAgentToolSet([$provider]))->execute('call-1', '  ', ['id' => 5]);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('unknown_tool', $result->getErrorCode());
		$this->assertSame(['id' => 5], $result->getArguments());
	}

	public function testNonexistentToolCannotPrepareEvenWhenOtherProviderIsConfirmable(): void {
		$provider = $this->createMock(IAgentConfirmableToolSet::class);
		$provider->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('change')]));
		$provider->method('getWarnings')->willReturn([]);
		$provider->expects($this->never())->method('prepareSuspension');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('not part of the resolved tool profiles');
		(new CompositeAgentToolSet([$provider]))->prepareSuspension('call-1', '  ', []);
	}

	public function testConfirmableToolMayIndicateNoSuspensionIsNecessary(): void {
		$provider = $this->createMock(IAgentConfirmableToolSet::class);
		$provider->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('change')]));
		$provider->method('getWarnings')->willReturn([]);
		$provider->expects($this->once())->method('prepareSuspension')->with(
			'call-1', 'change', ['value' => 'safe'], ['source' => 'trusted']
		)->willReturn(null);
		$this->assertNull((new CompositeAgentToolSet([$provider]))->prepareSuspension(
			'call-1', 'change', ['value' => 'safe'], ['source' => 'trusted']
		));
	}

	public function testSuspensionResumesOnlyThroughOwnerOfRequestedTool(): void {
		$regular = $this->createMock(IAgentToolSet::class);
		$regular->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('lookup')]));
		$regular->method('getWarnings')->willReturn([]);
		$regular->expects($this->never())->method('execute');
		$confirmed = $this->createMock(IAgentConfirmableToolSet::class);
		$confirmed->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('change')]));
		$confirmed->method('getWarnings')->willReturn([]);
		$done = AgentToolResult::success('call-1', 'change', [], ['success' => true]);
		$confirmed->expects($this->once())->method('resumeSuspension')->willReturn($done);
		$set = new CompositeAgentToolSet([$regular, $confirmed]);
		$this->assertSame($done, $set->resumeSuspension($this->suspension('change'), new AgentInteractionResponse('approve', AgentInteractionResponse::DECISION_APPROVE)));
	}

	public function testSuspendedUnknownToolCannotBeRoutedToUnrelatedProvider(): void {
		$provider = $this->createMock(IAgentConfirmableToolSet::class);
		$provider->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('lookup')]));
		$provider->method('getWarnings')->willReturn([]);
		$provider->expects($this->never())->method('resumeSuspension');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('no longer available');
		(new CompositeAgentToolSet([$provider]))->resumeSuspension(
			$this->suspension('unknown'),
			new AgentInteractionResponse('approve', AgentInteractionResponse::DECISION_APPROVE)
		);
	}

	private function capability(string $name): AgentCapability {
		return new AgentCapability($name, $name, $name, 'test', [], 0, [
			'type' => 'function', 'function' => [
				'name' => $name, 'parameters' => ['type' => 'object']
			]
		]);
	}

	private function suspension(string $toolName): AgentSuspension {
		return new AgentSuspension('suspend-1', AgentExecutionStatus::AWAITING_APPROVAL, [
			new AgentInteractionRequest('approve', AgentInteractionRequest::KIND_APPROVAL,
				new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, $toolName, []),
				str_repeat('a', 64), 'Confirm', 'Confirm action.'
			)
		], [], '2026-11-03T10:00:00+00:00');
	}
}
