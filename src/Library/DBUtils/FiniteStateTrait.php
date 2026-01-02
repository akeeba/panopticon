<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\DBUtils;

defined('AKEEBA') || die;

/**
 * Simple finite state machine trait
 *
 * @since 1.0.3
 */
trait FiniteStateTrait
{
	/**
	 * The current state of the FSM. NULL if uninitialised.
	 *
	 * @var   string|null
	 * @since 1.0.3
	 */
	protected ?string $fsmState = null;

	/**
	 * Advance the state of the FSM.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	protected function advanceState(): void
	{
		if (empty($this->fsmState))
		{
			$this->fsmState = static::FSM_STATES[0];

			return;
		}

		$idx = array_search($this->fsmState, static::FSM_STATES);

		if ($idx === false)
		{
			$this->fsmState = static::FSM_STATES[count(static::FSM_STATES) - 1];

			return;
		}

		$idx++;

		$this->fsmState = static::FSM_STATES[$idx] ?? static::FSM_STATES[count(static::FSM_STATES) - 1];
	}

	/**
	 * Have we reached the final state of the FSM?
	 *
	 * @return  bool
	 * @since   1.0.3
	 */
	protected function isFinalState(): bool
	{
		return $this->fsmState === static::FSM_STATES[count(static::FSM_STATES) - 1];
	}

	/**
	 * Get the current state of the FSM.
	 *
	 * @return  string
	 * @since   1.0.3
	 */
	protected function getCurrentState(): string
	{
		if (empty($this->fsmState))
		{
			$this->advanceState();
		}

		return $this->fsmState;
	}
}